<?php

namespace Acme\Bundle\SalesDocumentBundle\Layout\DataProvider;

use Acme\Bundle\SalesDocumentBundle\Entity\SalesDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Provides sales documents for the current customer user
 */
class SalesDocumentsDataProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    /**
     * Get sales documents for current user
     */
    public function getSalesDocuments(): array
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return [];
        }

        $user = $token->getUser();
        if (!$user || is_string($user)) {
            return [];
        }

        // Get all sales documents for current customer user
        $qb = $this->entityManager
            ->getRepository(SalesDocument::class)
            ->createQueryBuilder('sd');
            
        // Join with customer user to ensure we have access
        $qb->leftJoin('sd.customerUser', 'cu')
           ->where('cu.id = :userId')
           ->setParameter('userId', $user->getId())
           ->orderBy('sd.documentDate', 'DESC')
           ->addOrderBy('sd.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}