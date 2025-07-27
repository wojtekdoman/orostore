<?php

namespace Acme\Bundle\SalesDocumentBundle\Layout\DataProvider;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;

class CurrentUserProvider
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public function getCurrentUser(): ?CustomerUser
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        if ($user instanceof CustomerUser) {
            return $user;
        }

        return null;
    }

    public function getId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getId() : null;
    }
}