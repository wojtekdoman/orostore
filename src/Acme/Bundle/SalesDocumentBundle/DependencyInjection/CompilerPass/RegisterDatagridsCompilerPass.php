<?php

namespace Acme\Bundle\SalesDocumentBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers custom datagrids with the CustomerDashboardDatagridsProvider
 */
class RegisterDatagridsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('oro_commerce.content_widget.provider.customer_dashboard_datagrids')) {
            return;
        }

        $definition = $container->getDefinition('oro_commerce.content_widget.provider.customer_dashboard_datagrids');
        
        // Add our custom datagrids
        $customDatagrids = [
            'acme.salesdocument.dashboard.widget.latest_documents' => 'frontend-customer-dashboard-my-sales-documents-grid',
            'acme.salesdocument.dashboard.widget.unpaid_invoices' => 'frontend-customer-dashboard-unpaid-invoices-grid'
        ];
        
        $definition->addMethodCall('setDatagrids', [$customDatagrids]);
    }
}