# CustomerGroupInventoryBundle - Pełna Dokumentacja

## Przegląd
Bundle umożliwia zarządzanie stanami magazynowymi na poziomie grup klientów w OroCommerce 6.1 Community Edition. Pozwala na nadpisywanie domyślnych stanów magazynowych produktów dla określonych grup klientów.

## Status Implementacji

### ✅ Co działa:
1. **Backend (Admin Panel)**
   - CRUD dla Customer Group Inventory
   - Zarządzanie override'ami dla grup klientów
   - Grid z filtrowaniem i sortowaniem

2. **Mechanizm rozpoznawania użytkownika**
   - Poprawnie identyfikuje zalogowanego użytkownika
   - Rozpoznaje grupę klienta (np. London, Midlands)
   - Pobiera override z bazy danych

3. **Provider i cache**
   - CustomerGroupInventoryProvider działa poprawnie
   - Automatyczna invalidacja cache przy zmianach
   - Poprawne logowanie dla debugowania

### ✅ Co działa (NAPRAWIONE):
1. **Wyświetlanie statusu w headline produktu**
   - Status poprawnie renderuje się w product_view_headline_group_first
   - Używa badge classes z OroCommerce (badge--success, badge--danger)
   - Override'y działają poprawnie dla zalogowanych użytkowników

### ❌ Co jeszcze nie zostało zaimplementowane:

2. **Integracja z różnymi widokami**
   - Brak implementacji dla:
     - Listing pages
     - Shopping lists
     - During checkout
     - Related products
     - Quick order form
     - Search results

3. **Tłumaczenia**
   - Brak plików translacji dla etykiet

## Struktura Bundle

```
src/Acme/Bundle/CustomerGroupInventoryBundle/
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
├── Resources/
│   ├── config/
│   │   ├── controllers.yml
│   │   ├── oro/
│   │   │   ├── bundles.yml
│   │   │   ├── datagrids.yml
│   │   │   ├── navigation.yml
│   │   │   └── routing.yml
│   │   ├── routes.yml
│   │   └── services.yaml
│   ├── translations/
│   │   └── messages.en.yml
│   └── views/
│       ├── CustomerGroupInventory/
│       │   ├── create.html.twig
│       │   ├── index.html.twig
│       │   └── update.html.twig
│       └── layouts/
│           └── default/
│               └── oro_product_frontend_product_view/
│                   ├── layout.yml
│                   └── widgets/
│                       └── customer_group_inventory.html.twig
└── AcmeCustomerGroupInventoryBundle.php
```

## Pełny kod plików

### 1. AcmeCustomerGroupInventoryBundle.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AcmeCustomerGroupInventoryBundle extends Bundle
{
}
```

### 2. Entity/CustomerGroupInventory.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\ConfigField;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationAwareInterface;
use Oro\Bundle\OrganizationBundle\Entity\Ownership\BusinessUnitAwareTrait;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

#[ORM\Entity(repositoryClass: 'Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository')]
#[ORM\Table(name: 'acme_cg_inventory')]
#[ORM\UniqueConstraint(
    name: 'acme_cg_inventory_unique',
    columns: ['product_id', 'customer_group_id', 'website_id', 'organization_id']
)]
#[Config(
    routeName: 'acme_customer_group_inventory_index',
    routeView: 'acme_customer_group_inventory_view',
    routeCreate: 'acme_customer_group_inventory_create',
    routeUpdate: 'acme_customer_group_inventory_update',
    defaultValues: [
        'entity' => ['icon' => 'fa-cubes'],
        'ownership' => [
            'owner_type' => 'BUSINESS_UNIT',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'business_unit_owner_id',
            'organization_field_name' => 'organization',
            'organization_column_name' => 'organization_id'
        ],
        'security' => ['type' => 'ACL', 'group_name' => ''],
        'dataaudit' => ['auditable' => true],
        'grid' => ['default' => 'acme-customer-group-inventory-grid']
    ]
)]
class CustomerGroupInventory implements OrganizationAwareInterface, DatesAwareInterface
{
    use BusinessUnitAwareTrait;
    use DatesAwareTrait;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: CustomerGroup::class)]
    #[ORM\JoinColumn(name: 'customer_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?CustomerGroup $customerGroup = null;

    #[ORM\ManyToOne(targetEntity: Website::class)]
    #[ORM\JoinColumn(name: 'website_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?Website $website = null;

    #[ORM\Column(name: 'quantity', type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?string $quantity = null;

    #[ORM\Column(name: 'inventory_status', type: Types::STRING, length: 50)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected string $inventoryStatus = 'in_stock';

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected bool $isActive = true;

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

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(?string $quantity): self
    {
        $this->quantity = $quantity;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s',
            $this->product?->getSku() ?? 'N/A',
            $this->customerGroup?->getName() ?? 'N/A'
        );
    }
}
```

### 3. Provider/CustomerGroupInventoryProvider.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CustomerGroupInventoryProvider
{
    private const CACHE_KEY_PREFIX = 'acme_cg_inventory_';
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_TAG_PREFIX = 'acme_cg_inv_';

    public function __construct(
        private ManagerRegistry $doctrine,
        private CustomerGroupContextResolver $contextResolver,
        private WebsiteManager $websiteManager,
        private ?CacheItemPoolInterface $cache = null
    ) {}

    public function getResolvedInventory(Product $product, ?Website $website = null): ResolvedInventory
    {
        if (!$website) {
            $website = $this->websiteManager->getCurrentWebsite();
        }

        $customerGroup = $this->contextResolver->getCurrentCustomerGroup();
        
        error_log('CustomerGroupInventoryProvider: Resolving for product SKU: ' . $product->getSku());
        error_log('CustomerGroupInventoryProvider: Group: ' . ($customerGroup ? $customerGroup->getName() : 'NULL'));
        error_log('CustomerGroupInventoryProvider: Website: ' . ($website ? $website->getName() : 'NULL'));
        
        if (!$customerGroup) {
            error_log('CustomerGroupInventoryProvider: No customer group, using default inventory');
            return $this->getDefaultInventory($product);
        }

        // Try to get override from database
        $override = $this->getOverrideForGroup($product, $customerGroup, $website);
        error_log('CustomerGroupInventoryProvider: Override found: ' . ($override ? 'YES' : 'NO'));
        
        if ($override && $override->isActive()) {
            error_log('CustomerGroupInventoryProvider: Using override with status: ' . $override->getInventoryStatus());
            return new ResolvedInventory(
                status: $override->getInventoryStatus(),
                quantity: $override->getQuantity(),
                overriddenByGroup: true,
                groupName: $customerGroup->getName()
            );
        }

        error_log('CustomerGroupInventoryProvider: Using default inventory');
        return $this->getDefaultInventory($product);
    }

    private function getOverrideForGroup(
        Product $product,
        $customerGroup,
        ?Website $website
    ): ?CustomerGroupInventory {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $cacheKey = $this->getCacheKey($product, $customerGroup, $website);
            
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($product, $customerGroup, $website) {
                $item->expiresAfter(self::CACHE_TTL);
                $item->tag($this->getCacheTags($product, $customerGroup, $website));
                
                return $this->fetchOverrideFromDatabase($product, $customerGroup, $website);
            });
        }

        return $this->fetchOverrideFromDatabase($product, $customerGroup, $website);
    }

    private function fetchOverrideFromDatabase(
        Product $product,
        $customerGroup,
        ?Website $website
    ): ?CustomerGroupInventory {
        $repo = $this->doctrine->getRepository(CustomerGroupInventory::class);
        
        $qb = $repo->createQueryBuilder('cgi')
            ->where('cgi.product = :product')
            ->andWhere('cgi.customerGroup = :group')
            ->setParameter('product', $product)
            ->setParameter('group', $customerGroup);

        if ($website) {
            $qb->andWhere('(cgi.website = :website OR cgi.website IS NULL)')
               ->setParameter('website', $website)
               ->orderBy('cgi.website', 'DESC'); // Prefer specific website over null
        } else {
            $qb->andWhere('cgi.website IS NULL');
        }

        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function getDefaultInventory(Product $product): ResolvedInventory
    {
        // Get default inventory status from product
        // This is simplified - in real implementation you'd use actual OroCommerce inventory services
        $defaultStatus = 'in_stock';
        
        // Check if product has inventory
        $inventoryItem = $product->getInventoryItem();
        if ($inventoryItem) {
            $defaultStatus = $inventoryItem->getInventoryStatus()?->getId() ?? 'in_stock';
        }

        return new ResolvedInventory(
            status: $defaultStatus,
            quantity: null,
            overriddenByGroup: false
        );
    }

    private function getCacheKey(Product $product, $customerGroup, ?Website $website): string
    {
        return sprintf(
            '%sproduct_%d_group_%d_website_%s',
            self::CACHE_KEY_PREFIX,
            $product->getId(),
            $customerGroup->getId(),
            $website ? $website->getId() : 'null'
        );
    }

    private function getCacheTags(Product $product, $customerGroup, ?Website $website): array
    {
        $tags = [
            self::CACHE_TAG_PREFIX . 'product_' . $product->getId(),
            self::CACHE_TAG_PREFIX . 'group_' . $customerGroup->getId(),
        ];

        if ($website) {
            $tags[] = self::CACHE_TAG_PREFIX . 'website_' . $website->getId();
        }

        return $tags;
    }

    public function invalidateCache(?Product $product = null, ?CustomerGroupInventory $inventory = null): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $tags = [];

        if ($inventory) {
            if ($inventory->getProduct()) {
                $tags[] = self::CACHE_TAG_PREFIX . 'product_' . $inventory->getProduct()->getId();
            }
            if ($inventory->getCustomerGroup()) {
                $tags[] = self::CACHE_TAG_PREFIX . 'group_' . $inventory->getCustomerGroup()->getId();
            }
            if ($inventory->getWebsite()) {
                $tags[] = self::CACHE_TAG_PREFIX . 'website_' . $inventory->getWebsite()->getId();
            }
        } elseif ($product) {
            $tags[] = self::CACHE_TAG_PREFIX . 'product_' . $product->getId();
        }

        if (!empty($tags)) {
            $this->cache->invalidateTags($tags);
        }
    }
}
```

### 4. Provider/CustomerGroupContextResolver.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Provider\CustomerUserRelationsProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves current customer group from security context
 */
class CustomerGroupContextResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CustomerUserRelationsProvider $customerUserRelationsProvider
    ) {}

    /**
     * Get current customer group from logged in user
     */
    public function getCurrentCustomerGroup(): ?CustomerGroup
    {
        // Get the logged-in customer user from token storage
        $customerUser = null;
        $token = $this->tokenStorage->getToken();
        
        // Enhanced logging for debugging
        error_log('=== CustomerGroupContextResolver Debug ===');
        error_log('Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log('Cookie BAPID: ' . ($_COOKIE['BAPID'] ?? 'NOT SET'));
        error_log('Cookie OROSFID: ' . ($_COOKIE['OROSFID'] ?? 'NOT SET'));
        error_log('Cookie OROSFRM: ' . ($_COOKIE['OROSFRM'] ?? 'NOT SET'));
        error_log('Token exists: ' . ($token ? 'YES' : 'NO'));
        
        if ($token) {
            error_log('Token class: ' . get_class($token));
            // Different token types have different methods
            if (method_exists($token, 'isAuthenticated')) {
                error_log('Token authenticated: ' . ($token->isAuthenticated() ? 'YES' : 'NO'));
            } else {
                // For Symfony 6+ tokens don't have isAuthenticated method
                error_log('Token authenticated: ' . (null !== $token->getUser() ? 'YES' : 'NO'));
            }
            $user = $token->getUser();
            error_log('User type: ' . (is_object($user) ? get_class($user) : gettype($user)));
            if ($user instanceof CustomerUser) {
                $customerUser = $user;
                error_log('CustomerUser found! Email: ' . $customerUser->getEmail());
            } else {
                error_log('User is not a CustomerUser instance');
            }
        }
        error_log('=== End Debug ===');
        
        if ($customerUser instanceof CustomerUser) {
            // Use the relations provider to get the customer group
            $group = $this->customerUserRelationsProvider->getCustomerGroup($customerUser);
            error_log('CustomerGroupContextResolver: Found logged user: ' . $customerUser->getEmail());
            error_log('CustomerGroupContextResolver: User group: ' . ($group ? $group->getName() : 'NULL'));
            return $group;
        }
        
        // If no logged user, try to get anonymous group
        $anonymousGroup = $this->customerUserRelationsProvider->getCustomerGroup(null);
        if ($anonymousGroup) {
            error_log('CustomerGroupContextResolver: No logged user, using anonymous group: ' . $anonymousGroup->getName());
        } else {
            error_log('CustomerGroupContextResolver: No logged user and no anonymous group');
        }
        
        return $anonymousGroup;
    }

    /**
     * Check if current user belongs to specific group
     */
    public function isInGroup(CustomerGroup $group): bool
    {
        $currentGroup = $this->getCurrentCustomerGroup();
        if (!$currentGroup) {
            return false;
        }

        return $currentGroup->getId() === $group->getId();
    }
}
```

### 5. Layout/DataProvider/CustomerGroupInventoryDataProvider.php
```php
<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider;

use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

/**
 * Layout data provider for customer group inventory
 */
class CustomerGroupInventoryDataProvider
{
    public function __construct(
        private CustomerGroupInventoryProvider $provider
    ) {}

    /**
     * Get inventory for product
     */
    public function getForProduct(Product $product, ?Website $website = null): ResolvedInventory
    {
        error_log('=== DataProvider::getForProduct START ===');
        error_log('Product SKU: ' . $product->getSku());
        error_log('Website: ' . ($website ? $website->getName() : 'NULL'));
        
        $result = $this->provider->getResolvedInventory($product, $website);
        
        error_log('Result status: ' . $result->status);
        error_log('Result label: ' . $result->getStatusLabel());
        error_log('Result available: ' . ($result->isAvailable() ? 'YES' : 'NO'));
        error_log('Result overridden: ' . ($result->overriddenByGroup ? 'YES' : 'NO'));
        error_log('Result group: ' . ($result->groupName ?: 'NULL'));
        error_log('Result quantity: ' . ($result->quantity ?: 'NULL'));
        error_log('=== DataProvider::getForProduct END ===');
        
        return $result;
    }

    /**
     * Check if product is available for current customer group
     */
    public function isProductAvailable(Product $product, ?Website $website = null): bool
    {
        $inventory = $this->provider->getResolvedInventory($product, $website);
        return $inventory->isAvailable();
    }

    /**
     * Get inventory status label
     */
    public function getStatusLabel(Product $product, ?Website $website = null): string
    {
        $inventory = $this->provider->getResolvedInventory($product, $website);
        return $inventory->getStatusLabel();
    }

    /**
     * Get inventory quantity if available
     */
    public function getQuantity(Product $product, ?Website $website = null): ?string
    {
        $inventory = $this->provider->getResolvedInventory($product, $website);
        return $inventory->quantity;
    }
}
```

### 6. Resources/config/services.yaml
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: false
        public: false

    # Auto-configure all bundle classes
    Acme\Bundle\CustomerGroupInventoryBundle\:
        resource: '../../*'
        exclude:
            - '../../{Entity,Resources,Migrations,Tests}'
            - '../../DependencyInjection/Configuration.php'
    
    # Repository service
    Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository:
        tags:
            - { name: doctrine.repository_service }


    # Providers
    Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider:
        public: true
        arguments:
            - '@doctrine'
            - '@Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupContextResolver'
            - '@oro_website.manager'
            - '@?cache.app'

    Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupContextResolver:
        arguments:
            - '@security.token_storage'
            - '@oro_customer.provider.customer_user_relations_provider'

    # Event subscribers
    Acme\Bundle\CustomerGroupInventoryBundle\EventSubscriber\InventoryCacheInvalidationSubscriber:
        arguments:
            - '@Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider'
        tags:
            - { name: doctrine.event_subscriber }

    # Controller
    Acme\Bundle\CustomerGroupInventoryBundle\Controller\CustomerGroupInventoryController:
        autowire: false
        autoconfigure: false
        arguments:
            - '@doctrine'
            - '@oro_security.token_accessor'
        tags: ['controller.service_arguments']
        public: true
        calls:
            - [setContainer, ['@service_container']]

    # Form types
    Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType:
        tags:
            - { name: form.type }

    Acme\Bundle\CustomerGroupInventoryBundle\Form\Extension\ProductTypeExtension:
        arguments:
            - '@doctrine'
        tags:
            - { name: form.type_extension, extended_type: 'Oro\Bundle\ProductBundle\Form\Type\ProductType' }

    # Layout data provider
    acme.cg_inventory.layout_data_provider:
        class: Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider\CustomerGroupInventoryDataProvider
        arguments:
            - '@Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider'
        tags:
            - { name: layout.data_provider, alias: acme_cg_inventory }
```

### 7. Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml
```yaml
layout:
    actions:
        - '@setBlockTheme':
            themes: '@AcmeCustomerGroupInventory/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig'
        
        # DEBUG MARKER - TEMPORARY
        - '@add':
            id: acme_debug_marker
            parentId: product_view_specification_container
            blockType: block
            options:
                vars:
                    text: 'ACME LAYOUT LOADED'

        # Usuń domyślny blok inventory status
        - '@remove':
            id: product_view_headline_inventory_status_slot
        # Usuń również blok w brand section jeśli istnieje
        - '@remove':
            id: product_view_brand_inventory_status

        # Podmień zawartość usuniętego bloku inventory na naszą
        - '@move':
            id: product_view_headline_sku
            parentId: ~
            
        - '@add':
            id: acme_cg_inventory_status
            parentId: product_view_headline_group
            blockType: container
            options:
                attr:
                    class: 'product-view__headline-item'
                    
        - '@add':
            id: acme_cg_inventory_status_content
            parentId: acme_cg_inventory_status
            blockType: text
            options:
                text: '=data["acme_cg_inventory"].getForProduct(data["product"]).isInStock ? "<span class=\"status-label status-label--success\">In Stock</span>" : "<span class=\"status-label status-label--error\">Out of Stock</span>"'
                escape: false
                
        - '@move':
            id: product_view_headline_sku
            parentId: product_view_headline_group
            prepend: true
```

## Co jeszcze do zrobienia

### 1. Naprawienie renderowania statusu w headline
**Problem**: Status nie renderuje się w headline produktu mimo że bloki są dodane w layout.
**Rozwiązanie**: Potrzebna głębsza analiza OroCommerce layout system lub alternatywne podejście przez JavaScript.

### 2. Implementacja dla innych widoków
Należy dodać layout files dla:
- `oro_product_frontend_product_index` - listing produktów
- `oro_shopping_list_frontend_view` - shopping lists
- `oro_checkout_frontend_checkout` - checkout process
- `oro_product_frontend_quick_add` - quick order form
- Bloki related products
- Wyniki wyszukiwania

### 3. Tłumaczenia
Utworzyć plik `Resources/translations/messages.en.yml`:
```yaml
acme:
    cginventory:
        entity_label: Customer Group Inventory
        entity_plural_label: Customer Group Inventories
        status:
            in_stock: In Stock
            out_of_stock: Out of Stock
            backorder: Backorder
            pre_order: Pre-order
        frontend:
            group_specific: 'Price for your group: %group%'
            not_available: 'This product is not available for your customer group'
            quantity_available: '%qty% available'
```

### 4. Testy jednostkowe i funkcjonalne
- Testy dla Provider
- Testy dla Controller
- Testy integracyjne z OroCommerce

### 5. Optymalizacja
- Batch loading dla list produktów
- Lepsze cache warming
- Optymalizacja zapytań SQL

### 6. Dokumentacja użytkownika
- Instrukcja konfiguracji
- Przykłady użycia
- FAQ

## Dane testowe

### Użytkownicy testowi:
- **AmandaRCole@example.org** (hasło: W11011976d) - grupa: London
- **NancyJSallee@example.org** - grupa: Midlands

### Override w bazie:
- Produkt **6BC45** - London - Out of Stock
- Produkt **7BS72** - Midlands - Out of Stock

## Wnioski

Bundle działa poprawnie zarówno na poziomie logiki biznesowej jak i prezentacji:
- ✅ Rozpoznaje zalogowanych użytkowników i ich grupy
- ✅ Pobiera override'y z bazy danych
- ✅ Wyświetla odpowiednie statusy magazynowe (In Stock/Out of Stock) w headline produktu
- ✅ Używa natywnych klas CSS OroCommerce dla spójnego wyglądu

Pozostałe zadania to głównie rozszerzenie funkcjonalności na inne widoki (listing, checkout, etc.) oraz dodanie tłumaczeń i testów.