<?php

namespace Acme\Bundle\SalesDocumentBundle\Layout\DataProvider;

use Acme\Bundle\SalesDocumentBundle\Entity\SalesDocument;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\CurrencyBundle\Config\CurrencyConfigInterface;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;

class SalesDocumentDashboardProvider
{
    private EntityManagerInterface $entityManager;
    private TokenAccessorInterface $tokenAccessor;
    private CurrencyConfigInterface $currencyConfig;
    private NumberFormatter $numberFormatter;

    public function __construct(
        EntityManagerInterface $entityManager,
        TokenAccessorInterface $tokenAccessor,
        CurrencyConfigInterface $currencyConfig,
        NumberFormatter $numberFormatter
    ) {
        $this->entityManager = $entityManager;
        $this->tokenAccessor = $tokenAccessor;
        $this->currencyConfig = $currencyConfig;
        $this->numberFormatter = $numberFormatter;
    }

    /**
     * Get the latest sales documents for dashboard widget
     */
    public function getLatestDocuments(int $limit = 5): array
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user) {
            return [];
        }

        $qb = $this->entityManager->getRepository(SalesDocument::class)
            ->createQueryBuilder('doc')
            ->where('doc.customerUser = :user')
            ->setParameter('user', $user)
            ->orderBy('doc.documentDate', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get count of all sales documents
     */
    public function getDocumentCount(): int
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user) {
            return 0;
        }

        return $this->entityManager->getRepository(SalesDocument::class)
            ->count(['customerUser' => $user]);
    }

    /**
     * Get total unpaid amount for scorecard
     */
    public function getTotalUnpaidAmount(): array
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user) {
            return [
                'amount' => 0,
                'formatted' => $this->numberFormatter->formatCurrency(0, $this->currencyConfig->getDefaultCurrency())
            ];
        }

        $qb = $this->entityManager->getRepository(SalesDocument::class)
            ->createQueryBuilder('doc')
            ->select('SUM(doc.amount - COALESCE(doc.amountPaid, 0)) as totalUnpaid')
            ->where('doc.customerUser = :user')
            ->andWhere('doc.amount > COALESCE(doc.amountPaid, 0)')
            ->setParameter('user', $user);

        $result = $qb->getQuery()->getSingleScalarResult();
        $amount = $result ?: 0;

        return [
            'amount' => $amount,
            'formatted' => $this->numberFormatter->formatCurrency($amount, $this->currencyConfig->getDefaultCurrency())
        ];
    }

    /**
     * Get payment status distribution
     */
    public function getPaymentStatusDistribution(): array
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user) {
            return [
                'paid' => 0,
                'unpaid' => 0,
                'partially_paid' => 0
            ];
        }

        $qb = $this->entityManager->getRepository(SalesDocument::class)
            ->createQueryBuilder('doc')
            ->select(
                'SUM(CASE WHEN doc.amountPaid >= doc.amount THEN 1 ELSE 0 END) as paid',
                'SUM(CASE WHEN doc.amountPaid IS NULL OR doc.amountPaid = 0 THEN 1 ELSE 0 END) as unpaid',
                'SUM(CASE WHEN doc.amountPaid > 0 AND doc.amountPaid < doc.amount THEN 1 ELSE 0 END) as partially_paid'
            )
            ->where('doc.customerUser = :user')
            ->setParameter('user', $user);

        $result = $qb->getQuery()->getArrayResult();
        
        return $result[0] ?? [
            'paid' => 0,
            'unpaid' => 0,
            'partially_paid' => 0
        ];
    }
}