<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\EventSubscriber;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Invalidates cache when inventory records are changed
 */
class InventoryCacheInvalidationSubscriber implements EventSubscriber
{
    public function __construct(
        private CustomerGroupInventoryProvider $provider
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    private function invalidate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if (!$entity instanceof CustomerGroupInventory) {
            return;
        }

        // Clear cache for the affected product
        $this->provider->clearProductCache($entity->getProduct());
    }
}