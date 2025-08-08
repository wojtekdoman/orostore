<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\Extension;

use Acme\Bundle\CustomerGroupInventoryBundle\Form\EventSubscriber\ProductInventoryCollectionSubscriber;
use Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Extends product form to add customer group inventory collection
 */
class ProductTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private ManagerRegistry $doctrine
    ) {}

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('acmeCgInventories', CollectionType::class, [
            'entry_type' => CustomerGroupInventoryType::class,
            'entry_options' => [
                'disable_product_field' => true,
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'mapped' => false,
            'label' => 'acme.cginventory.collection.label',
            'required' => false,
            'prototype' => true,
            'prototype_name' => '__cg_inventory__',
        ]);

        $builder->addEventSubscriber(
            new ProductInventoryCollectionSubscriber($this->doctrine)
        );
    }
}