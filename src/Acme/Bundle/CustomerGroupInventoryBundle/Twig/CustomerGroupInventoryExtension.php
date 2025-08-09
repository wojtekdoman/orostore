<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Twig;

use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Doctrine\Persistence\ManagerRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for customer group inventory functions
 */
class CustomerGroupInventoryExtension extends AbstractExtension
{
    public function __construct(
        private CustomerGroupInventoryProvider $inventoryProvider,
        private ManagerRegistry $doctrine
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('acme_cg_inventory_status', [$this, 'getInventoryStatus']),
            new TwigFunction('acme_cg_inventory_label', [$this, 'getInventoryLabel']),
            new TwigFunction('acme_cg_inventory_is_available', [$this, 'isAvailable']),
            new TwigFunction('acme_cg_inventory_debug', [$this, 'getDebugInfo']),
        ];
    }

    /**
     * Get inventory status for a product
     */
    public function getInventoryStatus($product): string
    {
        $productEntity = $this->resolveProductEntity($product);
        
        if (!$productEntity) {
            // If we have an array with inventory_status field, use it as fallback
            if (is_array($product) && isset($product['inventory_status'])) {
                return $product['inventory_status'];
            }
            return 'in_stock';
        }
        
        $inventory = $this->inventoryProvider->getResolvedInventory($productEntity);
        return $inventory->status;
    }
    
    /**
     * Resolve product entity from various input formats
     */
    private function resolveProductEntity($product): ?Product
    {
        // If it's already a Product entity
        if ($product instanceof Product) {
            return $product;
        }
        
        // Handle array format
        if (is_array($product)) {
            // Try to get embedded product object
            if (isset($product['product']) && $product['product'] instanceof Product) {
                return $product['product'];
            }
            
            // Try to load by ID if we have it
            if (isset($product['id'])) {
                $productRepo = $this->doctrine->getRepository(Product::class);
                $productEntity = $productRepo->find($product['id']);
                if ($productEntity instanceof Product) {
                    return $productEntity;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get debug info for current context
     */
    public function getDebugInfo($product): string
    {
        $productEntity = $this->resolveProductEntity($product);
        
        if (!$productEntity) {
            if (is_array($product)) {
                return sprintf('Product is array with ID: %s, keys: %s', 
                    $product['id'] ?? 'NO ID',
                    implode(', ', array_slice(array_keys($product), 0, 5))
                );
            }
            return sprintf('Not a Product instance: %s', gettype($product));
        }
        
        $inventory = $this->inventoryProvider->getResolvedInventory($productEntity);
        return sprintf(
            'Group: %s, Overridden: %s, Status: %s, Product ID: %s',
            $inventory->groupName ?: 'NONE',
            $inventory->overriddenByGroup ? 'YES' : 'NO',
            $inventory->status,
            $productEntity->getId()
        );
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
        $productEntity = $this->resolveProductEntity($product);
        
        if (!$productEntity) {
            // Default to available if we can't resolve the product
            return true;
        }
        
        $inventory = $this->inventoryProvider->getResolvedInventory($productEntity);
        return $inventory->isAvailable();
    }
}