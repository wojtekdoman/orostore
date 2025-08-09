<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Provider\CustomerUserRelationsProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

/**
 * Resolves current customer group from security context
 */
class CustomerGroupContextResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CustomerUserRelationsProvider $customerUserRelationsProvider,
        private ?RequestStack $requestStack = null,
        private ?TokenAccessorInterface $tokenAccessor = null
    ) {}

    /**
     * Get current customer group from logged in user
     */
    public function getCurrentCustomerGroup(): ?CustomerGroup
    {
        // Try multiple methods to get the customer user
        $customerUser = null;
        
        // Method 1: Try TokenAccessor first (if available)
        if ($this->tokenAccessor) {
            $user = $this->tokenAccessor->getUser();
            if ($user instanceof CustomerUser) {
                $customerUser = $user;
            }
        }
        
        // Method 2: Try token storage if TokenAccessor didn't work
        if (!$customerUser) {
            $token = $this->tokenStorage->getToken();
            
            if ($token) {
                $user = $token->getUser();
                if ($user instanceof CustomerUser) {
                    $customerUser = $user;
                }
            }
        }
        
        if ($customerUser instanceof CustomerUser) {
            // Use the relations provider to get the customer group
            $group = $this->customerUserRelationsProvider->getCustomerGroup($customerUser);
            return $group;
        }
        
        // If no logged user, try to get anonymous group
        $anonymousGroup = $this->customerUserRelationsProvider->getCustomerGroup(null);
        return $anonymousGroup;
    }

    /**
     * Check if current user belongs to specific group
     */
    public function isInGroup(CustomerGroup $group): bool
    {
        $currentGroup = $this->getCurrentCustomerGroup();
        if (!$currentGroup) {
            return false;
        }

        return $currentGroup->getId() === $group->getId();
    }
}