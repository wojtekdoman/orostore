<?php

namespace Acme\Bundle\SalesDocumentBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds Sales Document datagrids to the Customer Dashboard Datagrids Provider
 */
class CustomerDashboardDatagridsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('oro_commerce.content_widget.provider.customer_dashboard_datagrids')) {
            return;
        }

        $definition = $container->getDefinition('oro_commerce.content_widget.provider.customer_dashboard_datagrids');
        
        // Add our custom datagrids
        $datagrids = [
            'acme.salesdocument.dashboard.widget.latest_documents' => 'frontend-customer-dashboard-my-sales-documents-grid',
            'acme.salesdocument.dashboard.widget.unpaid_invoices' => 'frontend-customer-dashboard-unpaid-invoices-grid',
        ];
        
        $definition->addMethodCall('setDatagrids', [$datagrids]);
    }
}