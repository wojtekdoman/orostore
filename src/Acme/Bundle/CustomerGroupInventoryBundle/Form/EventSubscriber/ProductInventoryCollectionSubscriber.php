<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\EventSubscriber;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Handles customer group inventory collection in product form
 */
class ProductInventoryCollectionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ManagerRegistry $doctrine
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SET_DATA => 'onPostSetData',
            FormEvents::POST_SUBMIT => 'onPostSubmit',
        ];
    }

    public function onPostSetData(FormEvent $event): void
    {
        $form = $event->getForm();
        $product = $event->getData();

        if (!$product instanceof Product || !$product->getId()) {
            return;
        }

        // Load existing inventory records for this product
        $repository = $this->doctrine->getRepository(CustomerGroupInventory::class);
        $inventories = $repository->findAllForProduct($product);

        if (!empty($inventories)) {
            $form->get('acmeCgInventories')->setData($inventories);
        }
    }

    public function onPostSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $product = $event->getData();

        if (!$product instanceof Product) {
            return;
        }

        $inventories = $form->get('acmeCgInventories')->getData();
        if (!$inventories) {
            return;
        }

        $em = $this->doctrine->getManagerForClass(CustomerGroupInventory::class);

        // Get existing records to handle deletions
        $existingInventories = [];
        if ($product->getId()) {
            $repository = $this->doctrine->getRepository(CustomerGroupInventory::class);
            $existing = $repository->findAllForProduct($product);
            foreach ($existing as $inv) {
                $existingInventories[$inv->getId()] = $inv;
            }
        }

        // Process submitted inventories
        foreach ($inventories as $inventory) {
            if (!$inventory instanceof CustomerGroupInventory) {
                continue;
            }

            // Set product and organization
            $inventory->setProduct($product);
            if ($product->getOrganization()) {
                $inventory->setOrganization($product->getOrganization());
            }

            // Remove from existing list if it's an update
            if ($inventory->getId()) {
                unset($existingInventories[$inventory->getId()]);
            }

            $em->persist($inventory);
        }

        // Remove inventories that were deleted in the form
        foreach ($existingInventories as $toDelete) {
            $em->remove($toDelete);
        }
    }
}