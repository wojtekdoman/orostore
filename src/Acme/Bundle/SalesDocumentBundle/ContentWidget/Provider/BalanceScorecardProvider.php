<?php

namespace Acme\Bundle\SalesDocumentBundle\ContentWidget\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CommerceBundle\ContentWidget\Provider\ScorecardInterface;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\PricingBundle\Rounding\PriceRoundingService;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Oro\Bundle\PricingBundle\Manager\UserCurrencyManager;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Acme\Bundle\SalesDocumentBundle\Entity\SalesDocument;

/**
 * Returns balance (sum of all unpaid invoices) for the scorecard widget
 */
class BalanceScorecardProvider implements ScorecardInterface
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private TokenAccessorInterface $tokenAccessor,
        private AuthorizationCheckerInterface $authorizationChecker,
        private UserCurrencyManager $userCurrencyManager,
        private NumberFormatter $numberFormatter,
        private PriceRoundingService $priceRoundingService
    ) {
    }

    public function getName(): string
    {
        return 'sales-documents-balance';
    }

    public function getLabel(): string
    {
        return 'acme.salesdocument.dashboard.scorecard.balance.label';
    }

    public function isVisible(): bool
    {
        return $this->authorizationChecker->isGranted('acme_sales_document_frontend_view');
    }

    public function getData(): ?string
    {
        $customerUser = $this->tokenAccessor->getUser();
        if (!$customerUser) {
            return null;
        }

        $currency = $this->userCurrencyManager->getUserCurrency();
        
        $repository = $this->doctrine->getRepository(SalesDocument::class);
        $qb = $repository->createQueryBuilder('doc');
        $qb->select('SUM(doc.amount - COALESCE(doc.amountPaid, 0)) as total')
           ->where('doc.customerUser = :customerUser')
           ->andWhere('doc.documentType = :type')
           ->andWhere('(doc.amountPaid IS NULL OR doc.amountPaid < doc.amount)')
           ->setParameter('customerUser', $customerUser)
           ->setParameter('type', 'invoice');
        
        $result = $qb->getQuery()->getSingleScalarResult();
        $amount = $result ?: 0;
        
        $price = Price::create($amount, $currency);
        $roundedPrice = $this->priceRoundingService->round($price);
        
        return $this->numberFormatter->formatCurrency($roundedPrice->getValue(), $currency);
    }
}