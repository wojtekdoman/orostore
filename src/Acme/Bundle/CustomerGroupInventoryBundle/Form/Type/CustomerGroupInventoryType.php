<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\Type;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerGroupInventoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'required' => true,
                'label' => 'acme.cginventory.product.label',
                'placeholder' => 'acme.cginventory.product.placeholder',
                'disabled' => $options['disable_product_field'] ?? false,
            ])
            ->add('customerGroup', EntityType::class, [
                'class' => CustomerGroup::class,
                'required' => true,
                'label' => 'acme.cginventory.customer_group.label',
                'placeholder' => 'acme.cginventory.customer_group.placeholder',
            ])
            ->add('website', EntityType::class, [
                'class' => Website::class,
                'required' => false,
                'label' => 'acme.cginventory.website.label',
                'placeholder' => 'acme.cginventory.website.placeholder',
                'empty_data' => null,
            ])
            ->add('quantity', NumberType::class, [
                'required' => true,
                'label' => 'acme.cginventory.quantity.label',
                'scale' => 6,
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('inventoryStatus', ChoiceType::class, [
                'required' => true,
                'label' => 'acme.cginventory.status.label',
                'choices' => [
                    'acme.cginventory.status.in_stock' => 'in_stock',
                    'acme.cginventory.status.out_of_stock' => 'out_of_stock',
                    'acme.cginventory.status.backorder' => 'backorder',
                    'acme.cginventory.status.pre_order' => 'pre_order',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
                'label' => 'acme.cginventory.is_active.label',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerGroupInventory::class,
            'disable_product_field' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'acme_customer_group_inventory';
    }
}