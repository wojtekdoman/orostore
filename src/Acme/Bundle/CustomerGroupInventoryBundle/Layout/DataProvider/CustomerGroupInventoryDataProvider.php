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
        return $this->provider->getResolvedInventory($product, $website);
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