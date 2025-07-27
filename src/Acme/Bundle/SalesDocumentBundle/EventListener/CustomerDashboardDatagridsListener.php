<?php

namespace Acme\Bundle\SalesDocumentBundle\EventListener;

use Oro\Bundle\CommerceBundle\Event\CustomerDashboardDatagridsEvent;

class CustomerDashboardDatagridsListener
{
    public function onGetDatagrids(CustomerDashboardDatagridsEvent $event): void
    {
        $event->addDatagrid('frontend-customer-dashboard-my-sales-documents-grid');
        $event->addDatagrid('frontend-customer-dashboard-unpaid-invoices-grid');
    }
}