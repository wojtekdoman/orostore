<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves current customer group from security context
 */
class CustomerGroupContextResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {}

    /**
     * Get current customer group from logged in user
     */
    public function getCurrentCustomerGroup(): ?CustomerGroup
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof CustomerUser) {
            return null;
        }

        $customer = $user->getCustomer();
        if (!$customer) {
            return null;
        }

        return $customer->getGroup();
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