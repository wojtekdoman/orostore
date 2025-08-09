<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider;

use Oro\Bundle\ProductBundle\Entity\Product;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Doctrine\Persistence\ManagerRegistry;

class RelatedProductsInventoryProvider
{
    public function __construct(
        private CustomerGroupInventoryProvider $inventoryProvider,
        private ManagerRegistry $doctrine
    ) {}

    /**
     * Get inventory status for product by ID
     */
    public function getInventoryStatusById(int $productId): array
    {
        $product = $this->doctrine->getRepository(Product::class)->find($productId);
        
        if (!$product) {
            return [
                'code' => 'in_stock',
                'label' => 'In Stock',
                'quantity' => null
            ];
        }
        
        $inventory = $this->inventoryProvider->getResolvedInventory($product);
        
        return [
            'code' => $inventory->status,
            'label' => $this->getStatusLabel($inventory->status),
            'quantity' => $inventory->quantity
        ];
    }
    
    /**
     * Get label for status code
     */
    private function getStatusLabel(string $statusCode): string
    {
        $labels = [
            'in_stock' => 'In Stock',
            'out_of_stock' => 'Out of Stock',
            'backorder' => 'Backorder',
            'pre_order' => 'Pre-Order'
        ];
        
        return $labels[$statusCode] ?? $statusCode;
    }
}