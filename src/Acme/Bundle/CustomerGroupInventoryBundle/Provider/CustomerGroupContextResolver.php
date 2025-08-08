<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Provider\CustomerUserRelationsProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves current customer group from security context
 */
class CustomerGroupContextResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CustomerUserRelationsProvider $customerUserRelationsProvider
    ) {}

    /**
     * Get current customer group from logged in user
     */
    public function getCurrentCustomerGroup(): ?CustomerGroup
    {
        // Get the logged-in customer user from token storage
        $customerUser = null;
        $token = $this->tokenStorage->getToken();
        error_log('Token exists: ' . ($token ? 'YES' : 'NO'));
        if ($token) {
            error_log('Token class: ' . get_class($token));
            $user = $token->getUser();
            error_log('User type: ' . (is_object($user) ? get_class($user) : gettype($user)));
            if ($user instanceof CustomerUser) {
                $customerUser = $user;
            } else {
                error_log('User is not a CustomerUser instance');
            }
        }
        
        if ($customerUser instanceof CustomerUser) {
            // Use the relations provider to get the customer group
            $group = $this->customerUserRelationsProvider->getCustomerGroup($customerUser);
            error_log('CustomerGroupContextResolver: Found logged user: ' . $customerUser->getEmail());
            error_log('CustomerGroupContextResolver: User group: ' . ($group ? $group->getName() : 'NULL'));
            return $group;
        }
        
        // If no logged user, try to get anonymous group
        $anonymousGroup = $this->customerUserRelationsProvider->getCustomerGroup(null);
        if ($anonymousGroup) {
            error_log('CustomerGroupContextResolver: No logged user, using anonymous group: ' . $anonymousGroup->getName());
        } else {
            error_log('CustomerGroupContextResolver: No logged user and no anonymous group');
        }
        
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