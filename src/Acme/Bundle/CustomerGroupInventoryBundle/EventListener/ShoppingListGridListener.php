<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\EventListener;

use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Updates inventory status in shopping list grid based on customer group
 */
class ShoppingListGridListener
{
    private CustomerGroupInventoryProvider $inventoryProvider;
    private ManagerRegistry $doctrine;
    private ?LoggerInterface $logger;
    private array $processedProducts = [];

    public function __construct(
        CustomerGroupInventoryProvider $inventoryProvider,
        ManagerRegistry $doctrine,
        ?LoggerInterface $logger = null
    ) {
        $this->inventoryProvider = $inventoryProvider;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    /**
     * Update inventory status after ORM results are fetched
     */
    public function onOrmResultAfter(OrmResultAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        
        // Only process shopping list edit grid
        if ($datagrid->getName() !== 'frontend-customer-user-shopping-list-edit-grid') {
            return;
        }
        
        $records = $event->getRecords();
        
        if ($this->logger) {
            $this->logger->info('ShoppingListGridListener: Processing ' . count($records) . ' records for grid: ' . $datagrid->getName());
            
            // Debug first record to see structure
            if (count($records) > 0) {
                $firstRecord = $records[0];
                $values = [];
                
                // Try to get all values
                foreach (['id', 'productId', 'product', 'sku', 'inventoryStatus'] as $field) {
                    try {
                        $value = $firstRecord->getValue($field);
                        $values[$field] = is_object($value) ? get_class($value) : $value;
                    } catch (\Exception $e) {
                        $values[$field] = 'ERROR: ' . $e->getMessage();
                    }
                }
                
                $this->logger->info('First record values: ' . json_encode($values));
            }
        }
        
        foreach ($records as $record) {
            $this->updateInventoryStatus($record);
        }
    }
    
    /**
     * Update inventory status for a single record
     */
    private function updateInventoryStatus(ResultRecord $record): void
    {
        try {
            // Get product ID from the record
            $productId = $record->getValue('productId');
            
            if (!$productId) {
                // Try getting product object directly
                $product = $record->getValue('product');
                if ($product instanceof Product) {
                    $productId = $product->getId();
                }
            }
            
            if (!$productId) {
                if ($this->logger) {
                    $this->logger->warning('No product ID found in record');
                }
                return;
            }
            
            if ($this->logger) {
                $this->logger->info('Found product ID: ' . $productId);
            }
            
            // Prevent processing same product multiple times
            if (isset($this->processedProducts[$productId])) {
                $record->setValue('inventoryStatus', $this->processedProducts[$productId]);
                return;
            }
            
            // Load product entity
            $product = $this->doctrine->getRepository(Product::class)->find($productId);
            
            if (!$product) {
                return;
            }
            
            // Get customer group inventory status
            $inventory = $this->inventoryProvider->getResolvedInventory($product);
            
            // Create inventory status HTML (similar to original template)
            $statusLabel = $this->getStatusLabel($inventory->status);
            $statusClass = $this->getStatusClass($inventory->status);
            
            $html = sprintf(
                '<span class="label label--%s">%s</span>',
                $statusClass,
                htmlspecialchars($statusLabel)
            );
            
            if ($inventory->quantity !== null && $inventory->overriddenByGroup) {
                $html .= sprintf(
                    ' <small>(%s)</small>',
                    htmlspecialchars($inventory->quantity)
                );
            }
            
            // Cache result
            $this->processedProducts[$productId] = $html;
            
            // Update record
            $record->setValue('inventoryStatus', $html);
            
            if ($this->logger) {
                $this->logger->info(sprintf(
                    'Updated inventory for product %d: %s',
                    $productId,
                    $inventory->status
                ));
            }
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Error updating inventory status: ' . $e->getMessage());
            }
        }
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
    
    /**
     * Get CSS class for inventory status
     */
    private function getStatusClass(string $status): string
    {
        $classes = [
            'in_stock' => 'success',
            'out_of_stock' => 'danger',
            'backorder' => 'warning',
            'pre_order' => 'info'
        ];
        
        return $classes[$status] ?? 'default';
    }
}