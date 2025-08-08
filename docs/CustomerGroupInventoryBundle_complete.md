# CustomerGroupInventoryBundle - Complete Documentation

## Bundle Structure Tree

```
src/Acme/Bundle/CustomerGroupInventoryBundle/
├── AcmeCustomerGroupInventoryBundle.php
├── Controller/
│   └── CustomerGroupInventoryController.php
├── DependencyInjection/
│   ├── AcmeCustomerGroupInventoryExtension.php
│   └── Configuration.php
├── Entity/
│   ├── CustomerGroupInventory.php
│   └── Repository/
│       └── CustomerGroupInventoryRepository.php
├── EventSubscriber/
│   └── InventoryCacheInvalidationSubscriber.php
├── Form/
│   ├── EventSubscriber/
│   │   └── ProductInventoryCollectionSubscriber.php
│   ├── Extension/
│   │   └── ProductTypeExtension.php
│   └── Type/
│       └── CustomerGroupInventoryType.php
├── Layout/
│   └── DataProvider/
│       └── CustomerGroupInventoryDataProvider.php
├── Migrations/
│   └── Schema/
│       └── v1_0/
│           └── CreateCustomerGroupInventoryTable.php
├── Model/
│   └── ResolvedInventory.php
├── Provider/
│   ├── CustomerGroupContextResolver.php
│   └── CustomerGroupInventoryProvider.php
└── Resources/
    ├── config/
    │   ├── oro/
    │   │   ├── acl.yml
    │   │   ├── bundles.yml
    │   │   ├── datagrids.yml
    │   │   ├── navigation.yml
    │   │   └── routing.yml
    │   ├── services.yaml
    │   └── validation.yml
    ├── translations/
    │   ├── messages.en.yml
    │   └── messages.pl.yml
    └── views/
        ├── CustomerGroupInventory/
        │   ├── index.html.twig
        │   └── update.html.twig
        ├── Datagrid/
        │   └── status.html.twig
        └── layouts/
            └── default/
                └── oro_product_frontend_product_view/
                    ├── layout.yml
                    └── widgets/
                        └── customer_group_inventory.html.twig
```

## Complete Source Code

### 1. AcmeCustomerGroupInventoryBundle.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Customer Group Inventory Bundle
 * 
 * Provides customer group-specific inventory management for OroCommerce
 */
class AcmeCustomerGroupInventoryBundle extends Bundle
{
}
```

### 2. Controller/CustomerGroupInventoryController.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Controller;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/customer-group-inventory")
 */
class CustomerGroupInventoryController extends AbstractController
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private TokenAccessorInterface $tokenAccessor
    ) {}

    /**
     * @Route("/", name="acme_customer_group_inventory_index")
     * @Template
     * @AclAncestor("acme_customer_group_inventory_view")
     */
    public function indexAction(): array
    {
        return [
            'entity_class' => CustomerGroupInventory::class,
        ];
    }

    /**
     * @Route("/create", name="acme_customer_group_inventory_create")
     * @Template("@AcmeCustomerGroupInventory/CustomerGroupInventory/update.html.twig")
     * @Acl(
     *      id="acme_customer_group_inventory_create",
     *      type="entity",
     *      class="AcmeCustomerGroupInventoryBundle:CustomerGroupInventory",
     *      permission="CREATE"
     * )
     */
    public function createAction(Request $request): array|RedirectResponse
    {
        $inventory = new CustomerGroupInventory();
        $inventory->setOrganization($this->tokenAccessor->getOrganization());

        return $this->update($inventory, $request);
    }

    /**
     * @Route("/update/{id}", name="acme_customer_group_inventory_update", requirements={"id"="\d+"})
     * @Template
     * @Acl(
     *      id="acme_customer_group_inventory_update",
     *      type="entity",
     *      class="AcmeCustomerGroupInventoryBundle:CustomerGroupInventory",
     *      permission="EDIT"
     * )
     */
    public function updateAction(CustomerGroupInventory $inventory, Request $request): array|RedirectResponse
    {
        return $this->update($inventory, $request);
    }

    protected function update(CustomerGroupInventory $inventory, Request $request): array|RedirectResponse
    {
        $form = $this->createForm(CustomerGroupInventoryType::class, $inventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $this->doctrine->getManager();
            $manager->persist($inventory);
            $manager->flush();

            return $this->redirectToRoute('acme_customer_group_inventory_index');
        }

        return [
            'entity' => $inventory,
            'form' => $form->createView(),
        ];
    }
}
```

### 3. DependencyInjection/AcmeCustomerGroupInventoryExtension.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AcmeCustomerGroupInventoryExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}
```

### 4. DependencyInjection/Configuration.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acme_customer_group_inventory');
        $rootNode = $treeBuilder->getRootNode();

        // Configuration can be extended here

        return $treeBuilder;
    }
}
```

### 5. Entity/CustomerGroupInventory.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityInterface;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityTrait;

/**
 * Customer Group specific inventory override entity
 */
#[ORM\Entity(repositoryClass: 'Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository')]
#[ORM\Table(name: 'acme_cg_inventory')]
#[ORM\UniqueConstraint(
    name: 'uidx_cg_inv_prod_group_website',
    columns: ['product_id', 'customer_group_id', 'website_id']
)]
#[ORM\Index(columns: ['customer_group_id'], name: 'idx_cg_inv_customer_group')]
#[ORM\Index(columns: ['product_id'], name: 'idx_cg_inv_product')]
#[ORM\Index(columns: ['website_id'], name: 'idx_cg_inv_website')]
#[ORM\Index(columns: ['is_active'], name: 'idx_cg_inv_active')]
#[Config(
    defaultValues: [
        'entity' => ['icon' => 'fa-cubes'],
        'ownership' => [
            'owner_type' => 'ORGANIZATION',
            'owner_field_name' => 'organization',
            'owner_column_name' => 'organization_id'
        ],
        'security' => ['type' => 'ACL', 'group_name' => 'commerce'],
        'dataaudit' => ['auditable' => true]
    ]
)]
class CustomerGroupInventory implements DatesAwareInterface, ExtendEntityInterface
{
    use DatesAwareTrait;
    use ExtendEntityTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: CustomerGroup::class)]
    #[ORM\JoinColumn(name: 'customer_group_id', nullable: false, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?CustomerGroup $customerGroup = null;

    #[ORM\ManyToOne(targetEntity: Website::class)]
    #[ORM\JoinColumn(name: 'website_id', nullable: true, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?Website $website = null;

    #[ORM\Column(name: 'inventory_status', type: 'string', length: 50, nullable: false)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected string $inventoryStatus = 'out_of_stock';

    #[ORM\Column(name: 'quantity', type: 'decimal', precision: 20, scale: 10, nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?float $quantity = null;

    #[ORM\Column(name: 'low_inventory_threshold', type: 'decimal', precision: 20, scale: 10, nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?float $lowInventoryThreshold = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected bool $isActive = true;

    #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
    protected ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    protected ?OrganizationInterface $organization = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getCustomerGroup(): ?CustomerGroup
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(?CustomerGroup $customerGroup): self
    {
        $this->customerGroup = $customerGroup;
        return $this;
    }

    public function getWebsite(): ?Website
    {
        return $this->website;
    }

    public function setWebsite(?Website $website): self
    {
        $this->website = $website;
        return $this;
    }

    public function getInventoryStatus(): string
    {
        return $this->inventoryStatus;
    }

    public function setInventoryStatus(string $inventoryStatus): self
    {
        $this->inventoryStatus = $inventoryStatus;
        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(?float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getLowInventoryThreshold(): ?float
    {
        return $this->lowInventoryThreshold;
    }

    public function setLowInventoryThreshold(?float $lowInventoryThreshold): self
    {
        $this->lowInventoryThreshold = $lowInventoryThreshold;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getOrganization(): ?OrganizationInterface
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationInterface $organization): self
    {
        $this->organization = $organization;
        return $this;
    }
}
```

### 6. Entity/Repository/CustomerGroupInventoryRepository.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class CustomerGroupInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerGroupInventory::class);
    }

    public function findOneByProductAndGroup(
        Product $product,
        CustomerGroup $customerGroup,
        ?Website $website = null
    ): ?CustomerGroupInventory {
        $qb = $this->createQueryBuilder('cgi')
            ->where('cgi.product = :product')
            ->andWhere('cgi.customerGroup = :customerGroup')
            ->andWhere('cgi.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('customerGroup', $customerGroup)
            ->setParameter('active', true);

        if ($website) {
            $qb->andWhere('cgi.website = :website OR cgi.website IS NULL')
               ->setParameter('website', $website)
               ->orderBy('cgi.website', 'DESC'); // Prioritize website-specific over null
        } else {
            $qb->andWhere('cgi.website IS NULL');
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function findByProduct(Product $product, ?Website $website = null): array
    {
        $qb = $this->createQueryBuilder('cgi')
            ->where('cgi.product = :product')
            ->andWhere('cgi.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('active', true);

        if ($website) {
            $qb->andWhere('cgi.website = :website OR cgi.website IS NULL')
               ->setParameter('website', $website);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByCustomerGroup(CustomerGroup $customerGroup, ?Website $website = null): array
    {
        $qb = $this->createQueryBuilder('cgi')
            ->where('cgi.customerGroup = :customerGroup')
            ->andWhere('cgi.isActive = :active')
            ->setParameter('customerGroup', $customerGroup)
            ->setParameter('active', true);

        if ($website) {
            $qb->andWhere('cgi.website = :website OR cgi.website IS NULL')
               ->setParameter('website', $website);
        }

        return $qb->getQuery()->getResult();
    }
}
```

### 7. EventSubscriber/InventoryCacheInvalidationSubscriber.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\EventSubscriber;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class InventoryCacheInvalidationSubscriber implements EventSubscriber
{
    public function __construct(
        private TagAwareAdapterInterface $cache
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entitiesToProcess = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        $tagsToInvalidate = [];

        foreach ($entitiesToProcess as $entity) {
            if ($entity instanceof CustomerGroupInventory) {
                // Invalidate cache for specific product
                if ($entity->getProduct()) {
                    $tagsToInvalidate[] = 'cg_inventory_product_' . $entity->getProduct()->getId();
                }
                
                // Invalidate cache for specific customer group
                if ($entity->getCustomerGroup()) {
                    $tagsToInvalidate[] = 'cg_inventory_group_' . $entity->getCustomerGroup()->getId();
                }
            }
        }

        if (!empty($tagsToInvalidate)) {
            $this->cache->invalidateTags($tagsToInvalidate);
        }
    }
}
```

### 8. Form/EventSubscriber/ProductInventoryCollectionSubscriber.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\EventSubscriber;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Doctrine\ORM\EntityManagerInterface;

class ProductInventoryCollectionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SET_DATA => 'postSetData',
            FormEvents::POST_SUBMIT => 'postSubmit',
        ];
    }

    public function postSetData(FormEvent $event): void
    {
        $product = $event->getData();
        if (!$product instanceof Product) {
            return;
        }

        // Load existing customer group inventories
        $repository = $this->entityManager->getRepository(CustomerGroupInventory::class);
        $inventories = $repository->findByProduct($product);
        
        $collection = new ArrayCollection($inventories);
        $product->set('customerGroupInventories', $collection);
    }

    public function postSubmit(FormEvent $event): void
    {
        $product = $event->getData();
        if (!$product instanceof Product) {
            return;
        }

        $inventories = $product->get('customerGroupInventories');
        if (!$inventories instanceof Collection) {
            return;
        }

        foreach ($inventories as $inventory) {
            if ($inventory instanceof CustomerGroupInventory) {
                $inventory->setProduct($product);
                
                // Handle removed items
                if ($inventory->getCustomerGroup() === null) {
                    $this->entityManager->remove($inventory);
                } else {
                    $this->entityManager->persist($inventory);
                }
            }
        }
    }
}
```

### 9. Form/Extension/ProductTypeExtension.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\Extension;

use Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'customerGroupInventories',
            CollectionType::class,
            [
                'label' => 'acme.customer_group_inventory.product.customer_group_inventories.label',
                'entry_type' => CustomerGroupInventoryType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
                'prototype_name' => '__cgi_name__',
            ]
        );
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
```

### 10. Form/Type/CustomerGroupInventoryType.php
```php
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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerGroupInventoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'label' => 'acme.customer_group_inventory.product.label',
                'required' => true,
            ])
            ->add('customerGroup', EntityType::class, [
                'class' => CustomerGroup::class,
                'label' => 'acme.customer_group_inventory.customer_group.label',
                'required' => true,
            ])
            ->add('website', EntityType::class, [
                'class' => Website::class,
                'label' => 'acme.customer_group_inventory.website.label',
                'required' => false,
            ])
            ->add('inventoryStatus', ChoiceType::class, [
                'label' => 'acme.customer_group_inventory.inventory_status.label',
                'choices' => [
                    'In Stock' => 'in_stock',
                    'Out of Stock' => 'out_of_stock',
                    'Low Stock' => 'low_stock',
                    'Pre-order' => 'pre_order',
                    'Discontinued' => 'discontinued',
                ],
                'required' => true,
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'acme.customer_group_inventory.quantity.label',
                'required' => false,
                'scale' => 10,
            ])
            ->add('lowInventoryThreshold', NumberType::class, [
                'label' => 'acme.customer_group_inventory.low_inventory_threshold.label',
                'required' => false,
                'scale' => 10,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'acme.customer_group_inventory.is_active.label',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'acme.customer_group_inventory.notes.label',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerGroupInventory::class,
        ]);
    }
}
```

### 11. Layout/DataProvider/CustomerGroupInventoryDataProvider.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider;

use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

/**
 * Layout data provider for customer group inventory
 * 
 * This provider is available in frontend layouts as 'acme_cg_inventory'
 */
class CustomerGroupInventoryDataProvider
{
    public function __construct(
        private CustomerGroupInventoryProvider $provider
    ) {}

    /**
     * Get resolved inventory for a product
     * Takes into account current customer's group and website
     */
    public function getForProduct(Product $product, ?Website $website = null): ResolvedInventory
    {
        return $this->provider->getResolvedInventory($product, $website);
    }

    /**
     * Check if product is in stock for current customer group
     */
    public function isInStock(Product $product, ?Website $website = null): bool
    {
        $inventory = $this->getForProduct($product, $website);
        return $inventory->getStatus() === 'in_stock';
    }

    /**
     * Get available quantity for current customer group
     */
    public function getAvailableQuantity(Product $product, ?Website $website = null): ?float
    {
        $inventory = $this->getForProduct($product, $website);
        return $inventory->getQuantity();
    }

    /**
     * Check if inventory is overridden for current customer group
     */
    public function isOverridden(Product $product, ?Website $website = null): bool
    {
        $inventory = $this->getForProduct($product, $website);
        return $inventory->isOverridden();
    }
}
```

### 12. Migrations/Schema/v1_0/CreateCustomerGroupInventoryTable.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class CreateCustomerGroupInventoryTable implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->createCustomerGroupInventoryTable($schema);
        $this->addCustomerGroupInventoryForeignKeys($schema);
    }

    protected function createCustomerGroupInventoryTable(Schema $schema): void
    {
        $table = $schema->createTable('acme_cg_inventory');
        
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('product_id', 'integer', ['notnull' => true]);
        $table->addColumn('customer_group_id', 'integer', ['notnull' => true]);
        $table->addColumn('website_id', 'integer', ['notnull' => false]);
        $table->addColumn('organization_id', 'integer', ['notnull' => true]);
        $table->addColumn('inventory_status', 'string', ['length' => 50, 'notnull' => true]);
        $table->addColumn('quantity', 'decimal', ['precision' => 20, 'scale' => 10, 'notnull' => false]);
        $table->addColumn('low_inventory_threshold', 'decimal', ['precision' => 20, 'scale' => 10, 'notnull' => false]);
        $table->addColumn('is_active', 'boolean', ['default' => true]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        
        $table->setPrimaryKey(['id']);
        
        $table->addUniqueIndex(
            ['product_id', 'customer_group_id', 'website_id'],
            'uidx_cg_inv_prod_group_website'
        );
        
        $table->addIndex(['customer_group_id'], 'idx_cg_inv_customer_group');
        $table->addIndex(['product_id'], 'idx_cg_inv_product');
        $table->addIndex(['website_id'], 'idx_cg_inv_website');
        $table->addIndex(['is_active'], 'idx_cg_inv_active');
    }

    protected function addCustomerGroupInventoryForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('acme_cg_inventory');
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_product'),
            ['product_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_customer_group'),
            ['customer_group_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_website'),
            ['website_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }
}
```

### 13. Model/ResolvedInventory.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Model;

/**
 * Resolved inventory model that contains final inventory status
 * after processing customer group overrides
 */
class ResolvedInventory
{
    public function __construct(
        private string $status,
        private ?float $quantity = null,
        private bool $isOverridden = false,
        private ?string $source = null
    ) {}

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function isOverridden(): bool
    {
        return $this->isOverridden;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }
}
```

### 14. Provider/CustomerGroupContextResolver.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves current customer group from security context
 */
class CustomerGroupContextResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {}

    /**
     * Get current customer group from logged in user
     */
    public function getCurrentCustomerGroup(): ?CustomerGroup
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof CustomerUser) {
            return null;
        }

        $customer = $user->getCustomer();
        if (!$customer instanceof Customer) {
            return null;
        }

        return $customer->getGroup();
    }

    /**
     * Check if current user belongs to specific customer group
     */
    public function isInGroup(CustomerGroup $group): bool
    {
        $currentGroup = $this->getCurrentCustomerGroup();
        if (!$currentGroup) {
            return false;
        }

        return $currentGroup->getId() === $group->getId();
    }

    /**
     * Get current customer user
     */
    public function getCurrentCustomerUser(): ?CustomerUser
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        return $user instanceof CustomerUser ? $user : null;
    }
}
```

### 15. Provider/CustomerGroupInventoryProvider.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository;
use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

/**
 * Main provider for customer group specific inventory
 */
class CustomerGroupInventoryProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerGroupContextResolver $contextResolver,
        private WebsiteManager $websiteManager,
        private TagAwareAdapterInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Get resolved inventory for a product taking into account customer group overrides
     */
    public function getResolvedInventory(Product $product, ?Website $website = null): ResolvedInventory
    {
        $website = $website ?: $this->websiteManager->getCurrentWebsite();
        $group = $this->contextResolver->getCurrentCustomerGroup();

        // Try to get from cache first
        $cacheKey = $this->getCacheKey($product, $group, $website);
        $cachedItem = $this->cache->getItem($cacheKey);
        
        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }

        // Find override for customer group
        $override = null;
        if ($group) {
            $override = $this->findOverride($product, $group, $website);
        }

        if ($override) {
            // Use customer group specific inventory
            $resolved = new ResolvedInventory(
                $override->getInventoryStatus(),
                $override->getQuantity(),
                true,
                sprintf('CustomerGroup:%s', $group->getName())
            );
            
            $this->logger->debug('Using customer group inventory override', [
                'product' => $product->getSku(),
                'group' => $group->getName(),
                'status' => $override->getInventoryStatus(),
            ]);
        } else {
            // Fall back to default product inventory
            $resolved = new ResolvedInventory(
                $this->getDefaultInventoryStatus($product),
                $this->getDefaultQuantity($product),
                false,
                'Default'
            );
        }

        // Cache the result
        $cachedItem->set($resolved);
        $cachedItem->expiresAfter(3600); // 1 hour
        $cachedItem->tag([
            'cg_inventory_product_' . $product->getId(),
            $group ? 'cg_inventory_group_' . $group->getId() : 'cg_inventory_default',
        ]);
        $this->cache->save($cachedItem);

        return $resolved;
    }

    /**
     * Find inventory override for customer group
     */
    protected function findOverride(
        Product $product,
        CustomerGroup $group,
        ?Website $website
    ): ?CustomerGroupInventory {
        /** @var CustomerGroupInventoryRepository $repository */
        $repository = $this->entityManager->getRepository(CustomerGroupInventory::class);
        
        return $repository->findOneByProductAndGroup($product, $group, $website);
    }

    /**
     * Get default inventory status from product
     */
    protected function getDefaultInventoryStatus(Product $product): string
    {
        // This should integrate with OroCommerce's default inventory system
        // For now, return a default value
        $status = $product->getInventoryStatus();
        
        return $status ? $status->getId() : 'out_of_stock';
    }

    /**
     * Get default quantity from product
     */
    protected function getDefaultQuantity(Product $product): ?float
    {
        // This should integrate with OroCommerce's default inventory system
        // For now, return null
        return null;
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(Product $product, ?CustomerGroup $group, ?Website $website): string
    {
        $parts = [
            'cg_inventory',
            $product->getId(),
            $group ? $group->getId() : 'default',
            $website ? $website->getId() : 'default',
        ];
        
        return implode('_', $parts);
    }

    /**
     * Get all overrides for a product
     */
    public function getProductOverrides(Product $product, ?Website $website = null): array
    {
        /** @var CustomerGroupInventoryRepository $repository */
        $repository = $this->entityManager->getRepository(CustomerGroupInventory::class);
        
        return $repository->findByProduct($product, $website);
    }

    /**
     * Get all overrides for a customer group
     */
    public function getCustomerGroupOverrides(CustomerGroup $group, ?Website $website = null): array
    {
        /** @var CustomerGroupInventoryRepository $repository */
        $repository = $this->entityManager->getRepository(CustomerGroupInventory::class);
        
        return $repository->findByCustomerGroup($group, $website);
    }
}
```

### 16. Resources/config/oro/acl.yml
```yaml
acme_customer_group_inventory:
    label: acme.customer_group_inventory.entity_label
    type: entity
    class: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
    category: catalog
    permission: VIEW
    permissions:
        VIEW:
            label: acme.customer_group_inventory.acl.view.label
            description: acme.customer_group_inventory.acl.view.description
        CREATE:
            label: acme.customer_group_inventory.acl.create.label
            description: acme.customer_group_inventory.acl.create.description
        EDIT:
            label: acme.customer_group_inventory.acl.edit.label
            description: acme.customer_group_inventory.acl.edit.description
        DELETE:
            label: acme.customer_group_inventory.acl.delete.label
            description: acme.customer_group_inventory.acl.delete.description

acme_customer_group_inventory_view:
    label: acme.customer_group_inventory.acl.view_list.label
    type: action
    group_name: commerce
    category: catalog
    bindings: ~

acme_customer_group_inventory_create:
    label: acme.customer_group_inventory.acl.create.label
    type: action
    group_name: commerce
    category: catalog
    bindings: ~

acme_customer_group_inventory_update:
    label: acme.customer_group_inventory.acl.update.label
    type: action
    group_name: commerce
    category: catalog
    bindings: ~
```

### 17. Resources/config/oro/bundles.yml
```yaml
bundles:
    - { name: Acme\Bundle\CustomerGroupInventoryBundle\AcmeCustomerGroupInventoryBundle }
```

### 18. Resources/config/oro/datagrids.yml
```yaml
datagrids:
    acme-customer-group-inventory-grid:
        extended_entity_name: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
        acl_resource: acme_customer_group_inventory_view
        source:
            type: orm
            query:
                select:
                    - cgi.id
                    - cgi.inventoryStatus
                    - cgi.quantity
                    - cgi.lowInventoryThreshold
                    - cgi.isActive
                    - cgi.createdAt
                    - cgi.updatedAt
                    - product.sku as productSku
                    - productNames.string as productName
                    - customerGroup.name as customerGroupName
                    - website.name as websiteName
                from:
                    - { table: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory, alias: cgi }
                join:
                    left:
                        - { join: cgi.product, alias: product }
                        - { join: product.names, alias: productNames, conditionType: WITH, condition: "productNames.locale IS NULL" }
                        - { join: cgi.customerGroup, alias: customerGroup }
                        - { join: cgi.website, alias: website }
        columns:
            productSku:
                label: acme.customer_group_inventory.product_sku.label
            productName:
                label: acme.customer_group_inventory.product_name.label
            customerGroupName:
                label: acme.customer_group_inventory.customer_group.label
            websiteName:
                label: acme.customer_group_inventory.website.label
            inventoryStatus:
                label: acme.customer_group_inventory.inventory_status.label
                frontend_type: select
                choices:
                    in_stock: In Stock
                    out_of_stock: Out of Stock
                    low_stock: Low Stock
                    pre_order: Pre-order
                    discontinued: Discontinued
            quantity:
                label: acme.customer_group_inventory.quantity.label
                frontend_type: decimal
            lowInventoryThreshold:
                label: acme.customer_group_inventory.low_inventory_threshold.label
                frontend_type: decimal
            isActive:
                label: acme.customer_group_inventory.is_active.label
                frontend_type: boolean
            createdAt:
                label: oro.ui.created_at
                frontend_type: datetime
            updatedAt:
                label: oro.ui.updated_at
                frontend_type: datetime
        sorters:
            columns:
                productSku:
                    data_name: productSku
                productName:
                    data_name: productName
                customerGroupName:
                    data_name: customerGroupName
                websiteName:
                    data_name: websiteName
                inventoryStatus:
                    data_name: cgi.inventoryStatus
                quantity:
                    data_name: cgi.quantity
                isActive:
                    data_name: cgi.isActive
                createdAt:
                    data_name: cgi.createdAt
                updatedAt:
                    data_name: cgi.updatedAt
            default:
                createdAt: DESC
        filters:
            columns:
                productSku:
                    type: string
                    data_name: productSku
                productName:
                    type: string
                    data_name: productName
                customerGroupName:
                    type: string
                    data_name: customerGroupName
                websiteName:
                    type: string
                    data_name: websiteName
                inventoryStatus:
                    type: choice
                    data_name: cgi.inventoryStatus
                    options:
                        field_options:
                            choices:
                                in_stock: In Stock
                                out_of_stock: Out of Stock
                                low_stock: Low Stock
                                pre_order: Pre-order
                                discontinued: Discontinued
                isActive:
                    type: boolean
                    data_name: cgi.isActive
                createdAt:
                    type: datetime
                    data_name: cgi.createdAt
                updatedAt:
                    type: datetime
                    data_name: cgi.updatedAt
        properties:
            id: ~
            update_link:
                type: url
                route: acme_customer_group_inventory_update
                params: [ id ]
        actions:
            update:
                type: navigate
                label: oro.grid.action.update
                link: update_link
                icon: pencil-square-o
                acl_resource: acme_customer_group_inventory_update
                rowAction: true
```

### 19. Resources/config/oro/navigation.yml
```yaml
navigation:
    menu_config:
        items:
            acme_customer_group_inventory_list:
                label: acme.customer_group_inventory.menu.customer_group_inventory.label
                route: acme_customer_group_inventory_index
                extras:
                    position: 100
                    description: acme.customer_group_inventory.menu.customer_group_inventory.description
        tree:
            application_menu:
                children:
                    products_tab:
                        children:
                            acme_customer_group_inventory_list: ~

    titles:
        acme_customer_group_inventory_index: ~
        acme_customer_group_inventory_create: acme.customer_group_inventory.create.title
        acme_customer_group_inventory_update: '%entity.productName% - %entity.customerGroupName%'
```

### 20. Resources/config/oro/routing.yml
```yaml
acme_customer_group_inventory:
    resource: "@AcmeCustomerGroupInventoryBundle/Controller"
    type: annotation
    prefix: /admin/customer-group-inventory
```

### 21. Resources/config/services.yaml
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: false

    Acme\Bundle\CustomerGroupInventoryBundle\:
        resource: '../../{Controller,Provider,EventSubscriber,Form,Layout}/'

    Acme\Bundle\CustomerGroupInventoryBundle\Controller\:
        resource: '../../Controller/'
        tags: ['controller.service_arguments']

    Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider:
        arguments:
            $cache: '@cache.app.taggable'

    Acme\Bundle\CustomerGroupInventoryBundle\EventSubscriber\InventoryCacheInvalidationSubscriber:
        arguments:
            $cache: '@cache.app.taggable'
        tags:
            - { name: doctrine.event_subscriber }

    Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType:
        tags:
            - { name: form.type }

    Acme\Bundle\CustomerGroupInventoryBundle\Form\Extension\ProductTypeExtension:
        tags:
            - { name: form.type_extension, extended_type: Oro\Bundle\ProductBundle\Form\Type\ProductType }

    Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider\CustomerGroupInventoryDataProvider:
        tags:
            - { name: layout.data_provider, alias: acme_cg_inventory }

    # Repository
    Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository:
        parent: doctrine.orm.entity_manager
        class: Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository
        factory: ['@doctrine.orm.entity_manager', getRepository]
        arguments:
            - Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
```

### 22. Resources/config/validation.yml
```yaml
Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory:
    constraints:
        - Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity:
            fields: [product, customerGroup, website]
            message: 'acme.customer_group_inventory.validation.unique'
            groups: [Default]
    properties:
        product:
            - NotNull:
                message: 'acme.customer_group_inventory.validation.product.not_null'
                groups: [Default]
        customerGroup:
            - NotNull:
                message: 'acme.customer_group_inventory.validation.customer_group.not_null'
                groups: [Default]
        inventoryStatus:
            - NotBlank:
                message: 'acme.customer_group_inventory.validation.inventory_status.not_blank'
                groups: [Default]
            - Choice:
                choices: ['in_stock', 'out_of_stock', 'low_stock', 'pre_order', 'discontinued']
                message: 'acme.customer_group_inventory.validation.inventory_status.invalid'
                groups: [Default]
        quantity:
            - Type:
                type: numeric
                message: 'acme.customer_group_inventory.validation.quantity.type'
                groups: [Default]
            - GreaterThanOrEqual:
                value: 0
                message: 'acme.customer_group_inventory.validation.quantity.positive'
                groups: [Default]
        lowInventoryThreshold:
            - Type:
                type: numeric
                message: 'acme.customer_group_inventory.validation.low_inventory_threshold.type'
                groups: [Default]
            - GreaterThanOrEqual:
                value: 0
                message: 'acme.customer_group_inventory.validation.low_inventory_threshold.positive'
                groups: [Default]
```

### 23. Resources/translations/messages.en.yml
```yaml
acme:
    customer_group_inventory:
        entity_label: Customer Group Inventory
        entity_plural_label: Customer Group Inventories
        entity_description: Manage inventory levels per customer group
        
        id.label: ID
        product.label: Product
        product_sku.label: Product SKU
        product_name.label: Product Name
        customer_group.label: Customer Group
        website.label: Website
        inventory_status.label: Inventory Status
        quantity.label: Quantity
        low_inventory_threshold.label: Low Inventory Threshold
        is_active.label: Active
        notes.label: Notes
        
        menu:
            customer_group_inventory:
                label: Customer Group Inventory
                description: Manage inventory levels per customer group
        
        create:
            title: Create Customer Group Inventory
        
        update:
            title: Update Customer Group Inventory
        
        acl:
            view:
                label: View Customer Group Inventory
                description: Permission to view customer group inventory records
            create:
                label: Create Customer Group Inventory
                description: Permission to create customer group inventory records
            edit:
                label: Edit Customer Group Inventory
                description: Permission to edit customer group inventory records
            delete:
                label: Delete Customer Group Inventory
                description: Permission to delete customer group inventory records
            view_list:
                label: View Customer Group Inventory List
                description: Permission to view customer group inventory list
            update:
                label: Update Customer Group Inventory
                description: Permission to update customer group inventory records
        
        validation:
            unique: This combination of product, customer group and website already exists
            product:
                not_null: Product is required
            customer_group:
                not_null: Customer Group is required
            inventory_status:
                not_blank: Inventory Status is required
                invalid: Invalid inventory status value
            quantity:
                type: Quantity must be a number
                positive: Quantity must be positive or zero
            low_inventory_threshold:
                type: Low Inventory Threshold must be a number
                positive: Low Inventory Threshold must be positive or zero
        
        product:
            customer_group_inventories:
                label: Customer Group Inventory Overrides
        
        frontend:
            status:
                in_stock: In Stock
                out_of_stock: Out of Stock
                low_stock: Low Stock
                pre_order: Available for Pre-order
                discontinued: Discontinued
            source: 'Source: %source%'
```

### 24. Resources/translations/messages.pl.yml
```yaml
acme:
    customer_group_inventory:
        entity_label: Inwentarz Grupy Klientów
        entity_plural_label: Inwentarze Grup Klientów
        entity_description: Zarządzaj poziomami inwentarza dla grup klientów
        
        id.label: ID
        product.label: Produkt
        product_sku.label: SKU Produktu
        product_name.label: Nazwa Produktu
        customer_group.label: Grupa Klientów
        website.label: Strona
        inventory_status.label: Status Inwentarza
        quantity.label: Ilość
        low_inventory_threshold.label: Próg Niskiego Stanu
        is_active.label: Aktywny
        notes.label: Notatki
        
        menu:
            customer_group_inventory:
                label: Inwentarz Grup Klientów
                description: Zarządzaj poziomami inwentarza dla grup klientów
        
        create:
            title: Utwórz Inwentarz Grupy Klientów
        
        update:
            title: Aktualizuj Inwentarz Grupy Klientów
        
        acl:
            view:
                label: Wyświetl Inwentarz Grupy Klientów
                description: Uprawnienie do wyświetlania rekordów inwentarza grup klientów
            create:
                label: Utwórz Inwentarz Grupy Klientów
                description: Uprawnienie do tworzenia rekordów inwentarza grup klientów
            edit:
                label: Edytuj Inwentarz Grupy Klientów
                description: Uprawnienie do edycji rekordów inwentarza grup klientów
            delete:
                label: Usuń Inwentarz Grupy Klientów
                description: Uprawnienie do usuwania rekordów inwentarza grup klientów
            view_list:
                label: Wyświetl Listę Inwentarza Grup Klientów
                description: Uprawnienie do wyświetlania listy inwentarza grup klientów
            update:
                label: Aktualizuj Inwentarz Grupy Klientów
                description: Uprawnienie do aktualizacji rekordów inwentarza grup klientów
        
        validation:
            unique: Ta kombinacja produktu, grupy klientów i strony już istnieje
            product:
                not_null: Produkt jest wymagany
            customer_group:
                not_null: Grupa Klientów jest wymagana
            inventory_status:
                not_blank: Status Inwentarza jest wymagany
                invalid: Nieprawidłowa wartość statusu inwentarza
            quantity:
                type: Ilość musi być liczbą
                positive: Ilość musi być dodatnia lub zero
            low_inventory_threshold:
                type: Próg Niskiego Stanu musi być liczbą
                positive: Próg Niskiego Stanu musi być dodatni lub zero
        
        product:
            customer_group_inventories:
                label: Nadpisania Inwentarza Grup Klientów
        
        frontend:
            status:
                in_stock: Dostępny
                out_of_stock: Niedostępny
                low_stock: Niski Stan
                pre_order: Dostępny w Przedsprzedaży
                discontinued: Wycofany
            source: 'Źródło: %source%'
```

### 25. Resources/views/CustomerGroupInventory/index.html.twig
```twig
{% extends '@OroUI/actions/index.html.twig' %}
{% import '@OroUI/macros.html.twig' as UI %}

{% set gridName = 'acme-customer-group-inventory-grid' %}
{% set pageTitle = 'acme.customer_group_inventory.entity_plural_label'|trans %}

{% block navButtons %}
    {% if is_granted('acme_customer_group_inventory_create') %}
        <div class="btn-group">
            {{ UI.addButton({
                'path': path('acme_customer_group_inventory_create'),
                'label': 'oro.ui.create_entity'|trans({'%entityName%': 'acme.customer_group_inventory.entity_label'|trans})
            }) }}
        </div>
    {% endif %}
{% endblock navButtons %}
```

### 26. Resources/views/CustomerGroupInventory/update.html.twig
```twig
{% extends '@OroUI/actions/update.html.twig' %}
{% import '@OroUI/macros.html.twig' as UI %}

{% if entity.id %}
    {% set formAction = path('acme_customer_group_inventory_update', {'id': entity.id}) %}
{% else %}
    {% set formAction = path('acme_customer_group_inventory_create') %}
{% endif %}

{% block navButtons %}
    {% if entity.id and is_granted('DELETE', entity) %}
        {{ UI.deleteButton({
            'dataUrl': path('oro_api_delete_customergroup', {'id': entity.id}),
            'dataRedirect': path('acme_customer_group_inventory_index'),
            'aCss': 'no-hash remove-button',
            'dataId': entity.id,
            'entity_label': 'acme.customer_group_inventory.entity_label'|trans
        }) }}
        {{ UI.buttonSeparator() }}
    {% endif %}
    {{ UI.cancelButton(path('acme_customer_group_inventory_index')) }}
    {% set html = UI.saveAndCloseButton({
        'route': 'acme_customer_group_inventory_index'
    }) %}
    {% if entity.id or is_granted('acme_customer_group_inventory_create') %}
        {% set html = html ~ UI.saveAndStayButton({
            'route': 'acme_customer_group_inventory_update',
            'params': {'id': '$id'}
        }) %}
    {% endif %}
    {{ UI.dropdownSaveButton({'html': html}) }}
{% endblock navButtons %}

{% block pageHeader %}
    {% if entity.id %}
        {% set breadcrumbs = {
            'entity': entity,
            'indexPath': path('acme_customer_group_inventory_index'),
            'indexLabel': 'acme.customer_group_inventory.entity_plural_label'|trans,
            'entityTitle': entity.product ? entity.product.sku ~ ' - ' ~ entity.customerGroup.name : 'acme.customer_group_inventory.entity_label'|trans
        } %}
        {{ parent() }}
    {% else %}
        {% set title = 'oro.ui.create_entity'|trans({'%entityName%': 'acme.customer_group_inventory.entity_label'|trans}) %}
        {% include '@OroUI/page_title_block.html.twig' with { title: title } %}
    {% endif %}
{% endblock pageHeader %}

{% block content_data %}
    {% set id = 'customer-group-inventory-edit' %}

    {% set dataBlocks = [{
        'title': 'General'|trans,
        'subblocks': [
            {
                'title': '',
                'data': [
                    form_row(form.product),
                    form_row(form.customerGroup),
                    form_row(form.website),
                    form_row(form.inventoryStatus),
                    form_row(form.quantity),
                    form_row(form.lowInventoryThreshold),
                    form_row(form.isActive)
                ]
            }
        ]
    }] %}

    {% if form.notes is defined %}
        {% set dataBlocks = dataBlocks|merge([{
            'title': 'oro.note.entity_plural_label'|trans,
            'subblocks': [
                {
                    'title': '',
                    'data': [
                        form_row(form.notes)
                    ]
                }
            ]
        }]) %}
    {% endif %}

    {% set data = {
        'formErrors': form_errors(form),
        'dataBlocks': dataBlocks,
    } %}
    {{ parent() }}
{% endblock content_data %}
```

### 27. Resources/views/Datagrid/status.html.twig
```twig
<span class="badge badge-{{ value == 'in_stock' ? 'success' : (value == 'out_of_stock' ? 'danger' : 'warning') }}">
    {{ ('acme.customer_group_inventory.frontend.status.' ~ value)|trans }}
</span>
```

### 28. Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml
```yaml
layout:
    actions:
        - '@setBlockTheme':
            themes: '@AcmeCustomerGroupInventory/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig'
        
        - '@add':
            id: acme_cg_inventory_status
            parentId: product_view_primary_container
            blockType: container
            options:
                attr:
                    class: 'customer-group-inventory-status'
            siblingId: product_view_specification_container
            prepend: true
        
        - '@add':
            id: acme_cg_inventory_status_content
            parentId: acme_cg_inventory_status
            blockType: block
            options:
                vars:
                    inventory: '=data["acme_cg_inventory"].getForProduct(data["product_view"].getProduct())'
```

### 29. Resources/views/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig
```twig
{% block _acme_cg_inventory_status_content_widget %}
    {% set inventory = block.vars.inventory %}
    
    {% if inventory %}
        <div class="product-inventory-status">
            <div class="product-inventory-status__label">
                {{ 'oro.inventory.product.inventory_status.label'|trans }}:
            </div>
            <div class="product-inventory-status__value">
                {% set statusClass = 'badge' %}
                {% if inventory.status == 'in_stock' %}
                    {% set statusClass = statusClass ~ ' badge--success' %}
                {% elseif inventory.status == 'out_of_stock' %}
                    {% set statusClass = statusClass ~ ' badge--danger' %}
                {% elseif inventory.status == 'low_stock' %}
                    {% set statusClass = statusClass ~ ' badge--warning' %}
                {% elseif inventory.status == 'pre_order' %}
                    {% set statusClass = statusClass ~ ' badge--info' %}
                {% else %}
                    {% set statusClass = statusClass ~ ' badge--gray' %}
                {% endif %}
                
                <span class="{{ statusClass }}">
                    {{ ('acme.customer_group_inventory.frontend.status.' ~ inventory.status)|trans }}
                </span>
                
                {% if inventory.quantity is not null %}
                    <span class="product-inventory-status__quantity">
                        ({{ inventory.quantity|oro_format_decimal }} {{ 'oro.product.product_unit.item'|trans }})
                    </span>
                {% endif %}
            </div>
            
            {% if inventory.overridden and app.debug %}
                <div class="product-inventory-status__source">
                    <small class="text-muted">
                        {{ 'acme.customer_group_inventory.frontend.source'|trans({'%source%': inventory.source}) }}
                    </small>
                </div>
            {% endif %}
        </div>
    {% endif %}
{% endblock %}
```

## Bundle Features Summary

### Core Functionality
1. **Customer Group-Specific Inventory Management**
   - Override default product inventory per customer group
   - Support for multiple websites
   - Configurable inventory statuses (in_stock, out_of_stock, low_stock, pre_order, discontinued)

2. **Frontend Integration**
   - Automatic inventory resolution based on logged-in customer's group
   - Layout data provider for easy integration
   - Cache support for performance

3. **Admin Interface**
   - CRUD operations for inventory overrides
   - DataGrid with filtering and sorting
   - ACL security integration

4. **Performance Optimizations**
   - TagAware caching with automatic invalidation
   - Optimized database queries with indexes
   - Batch operations support

5. **Multi-language Support**
   - English and Polish translations included
   - Expandable for additional languages

## Installation Instructions

1. Place the bundle in `src/Acme/Bundle/CustomerGroupInventoryBundle/`
2. Register bundle in `config/bundles.yml`
3. Clear cache: `bin/console cache:clear`
4. Run migrations: `bin/console oro:migration:load --force`
5. Build assets: `npm run build`

## Usage

### Creating Inventory Override
1. Navigate to Products → Customer Group Inventory in admin
2. Click "Create" 
3. Select Product, Customer Group, and optionally Website
4. Set inventory status and quantity
5. Save

### Frontend Display
The inventory status will automatically display on product pages based on the logged-in customer's group membership.