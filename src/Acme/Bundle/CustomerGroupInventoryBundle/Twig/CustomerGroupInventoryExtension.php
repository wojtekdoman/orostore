<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Twig;

use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for customer group inventory functions
 */
class CustomerGroupInventoryExtension extends AbstractExtension
{
    public function __construct(
        private CustomerGroupInventoryProvider $inventoryProvider
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('acme_cg_inventory_status', [$this, 'getInventoryStatus']),
            new TwigFunction('acme_cg_inventory_label', [$this, 'getInventoryLabel']),
            new TwigFunction('acme_cg_inventory_is_available', [$this, 'isAvailable']),
        ];
    }

    /**
     * Get inventory status for a product
     */
    public function getInventoryStatus($product): string
    {
        // Handle case where product is passed as array
        if (is_array($product)) {
            if (isset($product['product']) && $product['product'] instanceof Product) {
                $product = $product['product'];
            } else {
                // Return default status if we can't get the product object
                return 'in_stock';
            }
        }
        
        if (!$product instanceof Product) {
            return 'in_stock';
        }
        
        $inventory = $this->inventoryProvider->getResolvedInventory($product);
        return $inventory->status;
    }

    /**
     * Get inventory label for a product
     */
    public function getInventoryLabel($product): string
    {
        $status = $this->getInventoryStatus($product);
        
        return match($status) {
            'in_stock' => 'In Stock',
            'out_of_stock' => 'Out of Stock',
            'backorder' => 'Backorder',
            'pre_order' => 'Pre-order',
            default => ucwords(str_replace('_', ' ', $status))
        };
    }

    /**
     * Check if product is available for current customer group
     */
    public function isAvailable($product): bool
    {
        // Handle case where product is passed as array
        if (is_array($product)) {
            if (isset($product['product']) && $product['product'] instanceof Product) {
                $product = $product['product'];
            } else {
                return true;
            }
        }
        
        if (!$product instanceof Product) {
            return true;
        }
        
        $inventory = $this->inventoryProvider->getResolvedInventory($product);
        return $inventory->isAvailable();
    }
}