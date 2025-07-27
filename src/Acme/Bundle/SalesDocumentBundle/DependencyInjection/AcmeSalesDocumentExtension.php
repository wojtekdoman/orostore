<?php

namespace Acme\Bundle\SalesDocumentBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This is the class that loads and manages bundle configuration.
 */
class AcmeSalesDocumentExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }
    
    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator(__DIR__ . '/../Resources/config/oro');
        
        // Load datagrid configuration
        $datagridsFile = $fileLocator->locate('datagrids.yml');
        if (file_exists($datagridsFile)) {
            $datagrids = Yaml::parseFile($datagridsFile);
            $container->prependExtensionConfig('oro_datagrid', $datagrids);
        }
    }
}