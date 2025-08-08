Orocommerce CE 6.1.0
Theme Refreshing Teal


# CustomerGroupInventoryBundle - Diagnoza i Poprawki

## Problem
Domyślny status inventory z OroCommerce wyświetla się zamiast/obok statusu z CustomerGroupInventoryBundle dla zalogowanych klientów z przypisaną grupą.

## Diagnoza

### 1. Znalezione layouty OroCommerce
W OroCommerce istnieją następujące layouty dla inventory na stronie produktu:
- `InventoryBundle`: `low_inventory.yml`, `upcoming.yml` - dodają bloki do `product_view_specification_container`
- Bloki używają data providerów: `low_inventory`, `upcoming_product`

### 2. Brakujące elementy w Twoim bundle

#### A. Nie usuwasz domyślnych bloków inventory
Twój `layout.yml` tylko dodaje nowy blok, ale nie usuwa istniejących bloków z InventoryBundle.

#### B. Niewłaściwe umiejscowienie bloku
Dodajesz blok do `product_view_primary_container`, podczas gdy OroCommerce używa `product_view_specification_container`.

#### C. Brak integracji z domyślnym systemem inventory
Twój `CustomerGroupInventoryProvider::getDefaultInventoryStatus()` nie pobiera prawidłowo domyślnego statusu z produktu.

## Poprawki

### 1. Poprawiony layout.yml
**Lokalizacja**: `src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml`

```yaml
layout:
    actions:
        # Ustaw theme dla naszych widoków
        - '@setBlockTheme':
            themes: '@AcmeCustomerGroupInventory/layouts/default/oro_product_frontend_product_view/customer_group_inventory.html.twig'
        
        # USUŃ domyślne bloki inventory z OroCommerce (jeśli istnieją)
        - '@remove':
            id: low_inventory_label
        
        - '@remove':
            id: upcoming_label
            
        # Opcjonalnie usuń także domyślny blok statusu inventory jeśli istnieje
        - '@remove':
            id: product_inventory_status
        
        # Dodaj nasz własny blok statusu inventory
        - '@add':
            id: acme_cg_inventory_status
            parentId: product_view_specification_container  # Używamy tego samego kontenera co OroCommerce
            blockType: container
            options:
                attr:
                    class: 'customer-group-inventory-status'
            siblingId: product_price_subtree_update  # Umieszczamy przed ceną jak w OroCommerce
            prepend: true
        
        # Dodaj zawartość bloku
        - '@add':
            id: acme_cg_inventory_status_content
            parentId: acme_cg_inventory_status
            blockType: block
            options:
                vars:
                    inventory: '=data["acme_cg_inventory"].getForProduct(data["product_view"].getProduct())'
```

### 2. Poprawiony template customer_group_inventory.html.twig
**Lokalizacja**: `src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/views/layouts/default/oro_product_frontend_product_view/customer_group_inventory.html.twig`

```twig
{% block _acme_cg_inventory_status_content_widget %}
    {% set inventory = block.vars.inventory %}
    
    {% if inventory %}
        <div class="product-inventory-status">
            <div class="product-inventory-status__wrapper">
                {# Status Badge #}
                {% set statusClass = 'product-inventory-status__badge badge' %}
                {% if inventory.status == 'in_stock' %}
                    {% set statusClass = statusClass ~ ' badge--success' %}
                    {% set statusIcon = 'fa-check-circle' %}
                {% elseif inventory.status == 'out_of_stock' %}
                    {% set statusClass = statusClass ~ ' badge--danger' %}
                    {% set statusIcon = 'fa-times-circle' %}
                {% elseif inventory.status == 'low_stock' %}
                    {% set statusClass = statusClass ~ ' badge--warning' %}
                    {% set statusIcon = 'fa-exclamation-triangle' %}
                {% elseif inventory.status == 'pre_order' %}
                    {% set statusClass = statusClass ~ ' badge--info' %}
                    {% set statusIcon = 'fa-clock-o' %}
                {% else %}
                    {% set statusClass = statusClass ~ ' badge--gray' %}
                    {% set statusIcon = 'fa-question-circle' %}
                {% endif %}
                
                <span class="{{ statusClass }}">
                    <i class="fa {{ statusIcon }}"></i>
                    {{ ('acme.customer_group_inventory.frontend.status.' ~ inventory.status)|trans }}
                </span>
                
                {# Quantity display - tylko jeśli in_stock lub low_stock #}
                {% if inventory.quantity is not null and inventory.status in ['in_stock', 'low_stock'] %}
                    <span class="product-inventory-status__quantity">
                        <strong>{{ 'acme.customer_group_inventory.frontend.available'|trans }}:</strong>
                        {{ inventory.quantity|oro_format_decimal }} {{ 'oro.product.product_unit.item'|trans }}
                    </span>
                {% endif %}
                
                {# Debug info - tylko w trybie debug dla admina #}
                {% if inventory.isOverridden and app.debug and is_granted('ROLE_ADMINISTRATOR') %}
                    <div class="product-inventory-status__debug">
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i>
                            {{ 'acme.customer_group_inventory.frontend.source'|trans({'%source%': inventory.source}) }}
                        </small>
                    </div>
                {% endif %}
            </div>
        </div>
    {% endif %}
{% endblock %}
```

### 3. Poprawiony CustomerGroupInventoryProvider.php
**Lokalizacja**: `src/Acme/Bundle/CustomerGroupInventoryBundle/Provider/CustomerGroupInventoryProvider.php`

Popraw metody `getDefaultInventoryStatus()` i `getDefaultQuantity()`:

```php
/**
 * Get default inventory status from product
 */
protected function getDefaultInventoryStatus(Product $product): string
{
    // Sprawdź czy produkt ma ustawiony status inventory
    $inventoryStatus = $product->getInventoryStatus();
    
    if ($inventoryStatus && method_exists($inventoryStatus, 'getId')) {
        // OroCommerce używa EnumId dla statusów
        return $inventoryStatus->getId();
    }
    
    // Fallback na podstawie ilości jeśli dostępna
    $quantity = $this->getDefaultQuantity($product);
    if ($quantity !== null) {
        if ($quantity <= 0) {
            return 'out_of_stock';
        } elseif ($quantity < 10) { // Możesz dostosować próg
            return 'low_stock';
        }
        return 'in_stock';
    }
    
    // Domyślnie zwróć out_of_stock jeśli brak danych
    return 'out_of_stock';
}

/**
 * Get default quantity from product
 */
protected function getDefaultQuantity(Product $product): ?float
{
    // Spróbuj pobrać ilość z OroCommerce InventoryLevel
    // Wymaga dodania dependency injection dla InventoryLevelRepository
    
    // Tymczasowe rozwiązanie - sprawdź czy produkt ma metodę getInventoryLevel
    if (method_exists($product, 'getInventoryLevel')) {
        $level = $product->getInventoryLevel();
        if ($level && method_exists($level, 'getQuantity')) {
            return (float) $level->getQuantity();
        }
    }
    
    // Alternatywnie możesz użyć Doctrine do pobrania InventoryLevel
    // ale to wymaga wstrzyknięcia odpowiedniego repozytorium
    
    return null;
}
```

### 4. Dodaj dependency dla InventoryLevel (opcjonalnie)
**Lokalizacja**: `src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/services.yaml`

Dodaj do definicji `CustomerGroupInventoryProvider`:

```yaml
Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider:
    arguments:
        $cache: '@cache.app.taggable'
        $inventoryLevelRepository: '@oro_inventory.repository.inventory_level'  # Dodaj to
```

I zaktualizuj konstruktor w `CustomerGroupInventoryProvider.php`:

```php
use Oro\Bundle\InventoryBundle\Entity\Repository\InventoryLevelRepository;

public function __construct(
    private EntityManagerInterface $entityManager,
    private CustomerGroupContextResolver $contextResolver,
    private WebsiteManager $websiteManager,
    private TagAwareAdapterInterface $cache,
    private LoggerInterface $logger,
    private ?InventoryLevelRepository $inventoryLevelRepository = null  // Dodaj to
) {}
```

### 5. Dodaj brakujące tłumaczenia
**Lokalizacja**: `src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/translations/messages.en.yml`

Dodaj w sekcji `frontend`:

```yaml
frontend:
    available: Available
    status:
        in_stock: In Stock
        out_of_stock: Out of Stock
        low_stock: Low Stock
        pre_order: Available for Pre-order
        discontinued: Discontinued
    source: 'Source: %source%'
```

## Kroki instalacji poprawek

1. **Zastąp pliki** podanymi powyżej wersjami
2. **Wyczyść cache**:
   ```bash
   bin/console cache:clear
   ```
3. **Przebuduj assety** (jeśli masz własne style CSS):
   ```bash
   npm run build
   ```
4. **Testowanie**:
   - Zaloguj się jako klient z przypisaną grupą
   - Dodaj rekord w tabeli `acme_cg_inventory` dla produktu i grupy
   - Sprawdź czy na stronie produktu wyświetla się właściwy status

## Weryfikacja działania

### 1. Sprawdź logi
```bash
tail -f var/logs/dev.log | grep "Using customer group inventory"
```

### 2. Dodaj testowy rekord do bazy
```sql
INSERT INTO acme_cg_inventory (
    product_id, 
    customer_group_id, 
    website_id, 
    organization_id,
    inventory_status, 
    quantity, 
    is_active,
    created_at,
    updated_at
) VALUES (
    1,  -- ID produktu
    1,  -- ID grupy klientów
    1,  -- ID website (lub NULL dla wszystkich)
    1,  -- ID organizacji
    'in_stock',  -- Status
    100,  -- Ilość
    true,  -- Aktywny
    NOW(),
    NOW()
);
```

### 3. Debug w przeglądarce
Jeśli jesteś zalogowany jako administrator w trybie debug, zobaczysz informację o źródle statusu.

## Możliwe problemy

1. **Cache layoutów** - OroCommerce agresywnie cachuje layouty. Może być konieczne:
   ```bash
   rm -rf var/cache/*
   bin/console cache:warmup
   ```

2. **Priorytety bundle** - Upewnij się, że Twój bundle ładuje się PO InventoryBundle. Możesz to kontrolować w `bundles.yml`:
   ```yaml
   bundles:
       - { name: Oro\Bundle\InventoryBundle\OroInventoryBundle, priority: 100 }
       - { name: Acme\Bundle\CustomerGroupInventoryBundle\AcmeCustomerGroupInventoryBundle, priority: 110 }
   ```

3. **Brak CustomerUser w kontekście** - Jeśli użytkownik nie jest zalogowany, `CustomerGroupContextResolver` zwróci null i używany będzie domyślny status.