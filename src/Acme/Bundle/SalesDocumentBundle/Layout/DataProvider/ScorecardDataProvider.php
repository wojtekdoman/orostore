<?php

namespace Acme\Bundle\SalesDocumentBundle\Layout\DataProvider;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\CurrencyBundle\Config\CurrencyConfigInterface;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;

class ScorecardDataProvider
{
    private SalesDocumentDashboardProvider $dashboardProvider;
    private TokenAccessorInterface $tokenAccessor;
    private CurrencyConfigInterface $currencyConfig;
    private NumberFormatter $numberFormatter;

    public function __construct(
        SalesDocumentDashboardProvider $dashboardProvider,
        TokenAccessorInterface $tokenAccessor,
        CurrencyConfigInterface $currencyConfig,
        NumberFormatter $numberFormatter
    ) {
        $this->dashboardProvider = $dashboardProvider;
        $this->tokenAccessor = $tokenAccessor;
        $this->currencyConfig = $currencyConfig;
        $this->numberFormatter = $numberFormatter;
    }

    public function getUnpaidInvoicesData(): array
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user instanceof CustomerUser) {
            return [
                'value' => 0,
                'formatted' => $this->numberFormatter->formatCurrency(
                    0,
                    $this->currencyConfig->getDefaultCurrency()
                )
            ];
        }

        return $this->dashboardProvider->getTotalUnpaidAmount();
    }
}