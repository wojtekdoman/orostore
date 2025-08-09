<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\EventListener;

use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\ProductBundle\Entity\Product;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Adds inventory status to shopping list and checkout datagrids
 */
class ShoppingListDatagridListener
{
    public function __construct(
        private CustomerGroupInventoryProvider $inventoryProvider,
        private ManagerRegistry $doctrine
    ) {}

    /**
     * Add inventory status column to shopping list and checkout datagrid configuration
     */
    public function onBuildBefore(BuildBefore $event): void
    {
        $config = $event->getConfig();
        $gridName = $config->getName();
        
        // Only modify frontend shopping list and checkout line items grids
        $isShoppingList = str_contains($gridName, 'shopping-list') && str_contains($gridName, 'line-item');
        $isCheckout = str_contains($gridName, 'checkout') && str_contains($gridName, 'line-item');
        
        if (!$isShoppingList && !$isCheckout) {
            return;
        }
        
        // Add inventory status column
        $config->offsetSetByPath(
            '[columns][inventory_status]',
            [
                'label' => 'acme.cginventory.status.label',
                'type' => 'twig',
                'template' => '@AcmeCustomerGroupInventory/Datagrid/shopping_list_status.html.twig',
                'frontend_type' => 'html',
                'renderable' => true,
                'order' => 250
            ]
        );
        
        // Add property to fetch product data
        $config->offsetSetByPath(
            '[properties][product_id]',
            ['type' => 'callback', 'callable' => [$this, 'getProductId']]
        );
    }
    
    /**
     * Add inventory status data to results
     */
    public function onResultAfter(OrmResultAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $gridName = $datagrid->getName();
        
        // Only modify frontend shopping list and checkout line items grids
        $isShoppingList = str_contains($gridName, 'shopping-list') && str_contains($gridName, 'line-item');
        $isCheckout = str_contains($gridName, 'checkout') && str_contains($gridName, 'line-item');
        
        if (!$isShoppingList && !$isCheckout) {
            return;
        }
        
        $records = $event->getRecords();
        $productRepo = $this->doctrine->getRepository(Product::class);
        
        foreach ($records as $record) {
            $productId = $record->getValue('product_id');
            
            if ($productId) {
                $product = $productRepo->find($productId);
                if ($product) {
                    $inventory = $this->inventoryProvider->getResolvedInventory($product);
                    $record->setValue('inventory_status', $inventory->status);
                    $record->setValue('inventory_label', $this->getStatusLabel($inventory->status));
                    $record->setValue('inventory_overridden', $inventory->overriddenByGroup);
                }
            }
        }
    }
    
    /**
     * Get product ID from line item
     */
    public function getProductId($gridName, $keyName, $node): ?int
    {
        if (isset($node['product_id'])) {
            return $node['product_id'];
        }
        
        // Try to get from related product entity
        if (isset($node['product']) && is_object($node['product'])) {
            $product = $node['product'];
            if (method_exists($product, 'getId')) {
                return $product->getId();
            }
        }
        
        return null;
    }
    
    /**
     * Get human-readable status label
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'in_stock' => 'In Stock',
            'out_of_stock' => 'Out of Stock', 
            'backorder' => 'Backorder',
            'pre_order' => 'Pre-order',
            default => ucwords(str_replace('_', ' ', $status))
        };
    }
}