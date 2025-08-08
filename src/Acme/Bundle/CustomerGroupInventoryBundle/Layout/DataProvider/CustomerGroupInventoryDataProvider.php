<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider;

use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

/**
 * Layout data provider for customer group inventory
 */
class CustomerGroupInventoryDataProvider
{
    public function __construct(
        private CustomerGroupInventoryProvider $provider
    ) {}

    /**
     * Get inventory for product
     */
    public function getForProduct(Product $product, ?Website $website = null): ResolvedInventory
    {
        error_log('=== DataProvider::getForProduct START ===');
        error_log('Product SKU: ' . $product->getSku());
        error_log('Website: ' . ($website ? $website->getName() : 'NULL'));
        
        $result = $this->provider->getResolvedInventory($product, $website);
        
        error_log('Result status: ' . $result->status);
        error_log('Result label: ' . $result->getStatusLabel());
        error_log('Result available: ' . ($result->isAvailable() ? 'YES' : 'NO'));
        error_log('Result overridden: ' . ($result->overriddenByGroup ? 'YES' : 'NO'));
        error_log('Result group: ' . ($result->groupName ?: 'NULL'));
        error_log('Result quantity: ' . ($result->quantity ?: 'NULL'));
        error_log('=== DataProvider::getForProduct END ===');
        
        return $result;
    }

    /**
     * Check if product is available for current customer group
     */
    public function isProductAvailable(Product $product, ?Website $website = null): bool
    {
        $inventory = $this->provider->getResolvedInventory($product, $website);
        return $inventory->isAvailable();
    }

    /**
     * Get inventory status label
     */
    public function getStatusLabel(Product $product, ?Website $website = null): string
    {
        $inventory = $this->provider->getResolvedInventory($product, $website);
        return $inventory->getStatusLabel();
    }

    /**
     * Get inventory quantity if available
     */
    public function getQuantity(Product $product, ?Website $website = null): ?string
    {
        $inventory = $this->provider->getResolvedInventory($product, $website);
        return $inventory->quantity;
    }
}