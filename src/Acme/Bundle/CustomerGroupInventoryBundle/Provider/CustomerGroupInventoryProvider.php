<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository;
use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Main provider for customer group specific inventory
 */
class CustomerGroupInventoryProvider
{
    private array $localCache = [];
    private ?CustomerGroupInventoryRepository $repository = null;

    public function __construct(
        private ManagerRegistry $doctrine,
        private CustomerGroupContextResolver $contextResolver,
        private WebsiteManager $websiteManager,
        private ?CacheItemPoolInterface $cache = null
    ) {}
    
    private function getRepository(): CustomerGroupInventoryRepository
    {
        if (!$this->repository) {
            $this->repository = $this->doctrine->getRepository(CustomerGroupInventory::class);
        }
        return $this->repository;
    }

    /**
     * Get resolved inventory for product based on current context
     */
    public function getResolvedInventory(Product $product, ?Website $website = null): ResolvedInventory
    {
        $website = $website ?: $this->websiteManager->getCurrentWebsite();
        $group = $this->contextResolver->getCurrentCustomerGroup();

        // Build cache key
        $key = $this->getCacheKey($product, $group, $website);

        // Check local cache first
        if (isset($this->localCache[$key])) {
            return $this->localCache[$key];
        }

        // Check persistent cache if available
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($key);
            if ($cacheItem->isHit()) {
                $this->localCache[$key] = $cacheItem->get();
                return $this->localCache[$key];
            }
        }

        // Fetch from database
        $inventory = $this->resolveInventory($product, $group, $website);

        // Store in caches
        $this->localCache[$key] = $inventory;
        if ($this->cache) {
            $cacheItem->set($inventory);
            $cacheItem->expiresAfter(3600); // 1 hour
            $this->cache->save($cacheItem);
        }

        return $inventory;
    }

    /**
     * Resolve inventory from database
     */
    private function resolveInventory(Product $product, $group, ?Website $website): ResolvedInventory
    {
        error_log('CustomerGroupInventoryProvider: Resolving for product SKU: ' . $product->getSku());
        error_log('CustomerGroupInventoryProvider: Group: ' . ($group ? $group->getName() : 'NULL'));
        error_log('CustomerGroupInventoryProvider: Website: ' . ($website ? $website->getName() : 'NULL'));
        
        // Find override for customer group
        if ($group) {
            $override = $this->getRepository()->findOneFor($product, $group, $website);
            error_log('CustomerGroupInventoryProvider: Override found: ' . ($override ? 'YES' : 'NO'));
            
            if ($override && $override->getIsActive()) {
                error_log('CustomerGroupInventoryProvider: Using override with status: ' . $override->getInventoryStatus());
                return new ResolvedInventory(
                    $override->getInventoryStatus(),
                    $override->getQuantity(),
                    true,
                    $group->getName()
                );
            }
        }

        error_log('CustomerGroupInventoryProvider: Using default inventory');
        // Fallback to default product inventory
        return $this->getDefaultInventory($product);
    }

    /**
     * Get default inventory from product
     */
    private function getDefaultInventory(Product $product): ResolvedInventory
    {
        // Check if product has inventory status method
        $status = 'in_stock';
        $quantity = null;

        if (method_exists($product, 'getInventoryStatus')) {
            $inventoryStatus = $product->getInventoryStatus();
            if ($inventoryStatus) {
                $status = (string) $inventoryStatus->getId();
            }
        }

        // Try to get quantity from inventory levels if available
        if (method_exists($product, 'getInventoryLevel')) {
            $level = $product->getInventoryLevel();
            if ($level) {
                $quantity = (string) $level->getQuantity();
            }
        }

        return new ResolvedInventory($status, $quantity, false);
    }

    /**
     * Build cache key
     */
    private function getCacheKey(Product $product, $group, ?Website $website): string
    {
        return sprintf(
            'cgi_%d_%d_%d',
            $product->getId(),
            $group?->getId() ?? 0,
            $website?->getId() ?? 0
        );
    }

    /**
     * Clear local cache
     */
    public function clearLocalCache(): void
    {
        $this->localCache = [];
    }

    /**
     * Clear all caches for specific product
     */
    public function clearProductCache(Product $product): void
    {
        // Clear local cache entries for this product
        $productId = $product->getId();
        foreach (array_keys($this->localCache) as $key) {
            if (str_starts_with($key, "cgi_{$productId}_")) {
                unset($this->localCache[$key]);
            }
        }

        // Clear persistent cache if available
        if ($this->cache) {
            // Would need to track keys or use tags for efficient clearing
            $this->cache->clear();
        }
    }
}