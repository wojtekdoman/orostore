<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acme_customer_group_inventory');
        $rootNode = $treeBuilder->getRootNode();
        
        return $treeBuilder;
    }
}