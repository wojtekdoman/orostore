<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Autocomplete;

use Oro\Bundle\ProductBundle\Provider\ProductAutocompleteProvider;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Decorates autocomplete results with customer group inventory status
 */
class SearchAutocompleteDecorator
{
    private ProductAutocompleteProvider $innerProvider;
    private CustomerGroupInventoryProvider $inventoryProvider;
    private ManagerRegistry $doctrine;

    public function __construct(
        ProductAutocompleteProvider $innerProvider,
        CustomerGroupInventoryProvider $inventoryProvider,
        ManagerRegistry $doctrine
    ) {
        $this->innerProvider = $innerProvider;
        $this->inventoryProvider = $inventoryProvider;
        $this->doctrine = $doctrine;
    }

    /**
     * Get autocomplete data with customer group inventory status
     */
    public function getAutocompleteData(string $searchString, string $searchSessionId): array
    {
        // Get original autocomplete data
        $data = $this->innerProvider->getAutocompleteData($searchString, $searchSessionId);
        
        // Update inventory status for products
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as &$productData) {
                if (isset($productData['id'])) {
                    $product = $this->doctrine->getRepository(Product::class)->find($productData['id']);
                    if ($product) {
                        $inventory = $this->inventoryProvider->getResolvedInventory($product);
                        
                        // Override inventory status
                        $productData['inventory_status'] = $inventory->status;
                        $productData['inventory_status_label'] = $this->getStatusLabel($inventory->status);
                        
                        // Add quantity if available
                        if ($inventory->quantity !== null) {
                            $productData['inventory_quantity'] = $inventory->quantity;
                        }
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get label for inventory status
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'in_stock' => 'In Stock',
            'out_of_stock' => 'Out of Stock',
            'backorder' => 'Backorder',
            'pre_order' => 'Pre-Order'
        ];
        
        return $labels[$status] ?? $status;
    }
}