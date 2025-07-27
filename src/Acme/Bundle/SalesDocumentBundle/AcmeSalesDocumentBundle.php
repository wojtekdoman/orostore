<?php

namespace Acme\Bundle\SalesDocumentBundle;

use Acme\Bundle\SalesDocumentBundle\DependencyInjection\CompilerPass\RegisterDatagridsCompilerPass;
use Acme\Bundle\SalesDocumentBundle\DependencyInjection\Compiler\CustomerDashboardDatagridsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * AcmeSalesDocumentBundle for Sales Documents functionality
 */
class AcmeSalesDocumentBundle extends Bundle
{
    /**
     * {@inheritdoc}
     * 
     * This allows the bundle to inherit from OroFrontendBundle,
     * making it easier to override templates and layouts
     */
    public function getParent(): ?string
    {
        return 'OroFrontendBundle';
    }
    
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        
        $container->addCompilerPass(new RegisterDatagridsCompilerPass());
        $container->addCompilerPass(new CustomerDashboardDatagridsPass());
    }
}