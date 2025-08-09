# CustomerGroupInventoryBundle - Mechanizm Wyświetlania

## 🎯 Zasada Działania

Bundle wykorzystuje **jednolity mechanizm** dla wszystkich widoków oparty na:
1. **Layout System OroCommerce** - dodawanie/usuwanie bloków bez modyfikacji core'a
2. **Data Provider** - centralne źródło danych o stanach magazynowych
3. **Block Templates** - spójne szablony Twig dla wszystkich widoków

## 📋 Implementowane Widoki

### 1. Product Detail Page ✅
**Lokalizacja:** `oro_product_frontend_product_view/layout.yml`
```yaml
# Usuwa domyślny blok
- '@remove':
    id: product_view_headline_inventory_status_slot
    
# Dodaje nasz blok
- '@add':
    id: acme_cg_inventory_status
    parentId: product_view_headline_group_first
    blockType: container
```
**Efekt:** Badge ze statusem w headline produktu

### 2. Product Listing (Gallery/Compact/List) ✅
**Lokalizacja:** `imports/oro_product_list_item/customer_group_inventory.yml`
```yaml
# Usuwa domyślny status
- '@remove':
    id: __product_inventory_status_label
    
# Dodaje nasz status
- '@add':
    id: __acme_cg_inventory_status
    parentId: __product_sku_container
```
**Efekt:** Mini badge przy każdym produkcie w liście

### 3. Shopping List ✅
**Lokalizacja:** `oro_shopping_list_frontend_view/layout.yml`
- JavaScript injection do dodania badge'y
- Wykorzystuje data attributes ustawione przez backend

### 4. Checkout ✅
**Lokalizacja:** `oro_checkout_frontend_checkout/layout.yml`
- Walidacja dostępności produktów
- Ostrzeżenia dla produktów out of stock
- Notification flash messages

### 5. Related Products ✅
**Lokalizacja:** `imports/oro_product_grid/customer_group_inventory.yml`
- Używa tego samego mechanizmu co listing
- Dodatkowa ikona dla grup specjalnych

### 6. Quick Order Form ✅
**Lokalizacja:** `oro_product_frontend_quick_add/layout.yml`
- AJAX endpoint do sprawdzania dostępności
- Real-time walidacja przy wyborze produktu
- Potwierdzenie przy składaniu zamówienia

### 7. Search Results ✅
**Lokalizacja:** `oro_website_search_result_frontend/layout.yml`
- Filtry dostępności
- JavaScript do ukrywania/pokazywania produktów
- Licznik wyników

## 🔧 Kluczowe Komponenty

### Data Provider
```php
class CustomerGroupInventoryDataProvider
{
    public function getForProduct(Product $product): ResolvedInventory
    {
        return $this->provider->getResolvedInventory($product);
    }
}
```
**Rejestracja:**
```yaml
acme.cg_inventory.layout_data_provider:
    tags:
        - { name: layout.data_provider, alias: acme_cg_inventory }
```

### Template Widget (uniwersalny)
```twig
{% block _acme_cg_inventory_status_label_widget %}
    {% set inventory = block.vars.inventory|default(null) %}
    {% if inventory %}
        {% if inventory.status == 'in_stock' %}
            <span class="badge badge--success">In Stock</span>
        {% elseif inventory.status == 'out_of_stock' %}
            <span class="badge badge--danger">Out of Stock</span>
        {% endif %}
    {% endif %}
{% endblock %}
```

### AJAX Endpoint
```php
#[Route(path: '/check', name: 'acme_customer_group_inventory_check')]
public function checkInventoryAction(Request $request): JsonResponse
{
    $inventory = $this->inventoryProvider->getResolvedInventory($product);
    return new JsonResponse([
        'status' => $inventory->status,
        'is_available' => $inventory->isAvailable()
    ]);
}
```

## 🎨 Wzorzec Implementacji (Do Zapamiętania)

### Krok 1: Utwórz Layout YAML
```yaml
layout:
    actions:
        # 1. Ustaw theme
        - '@setBlockTheme':
            themes: '@AcmeCustomerGroupInventory/layouts/default/[PATH]/template.html.twig'
        
        # 2. Usuń domyślne bloki (jeśli istnieją)
        - '@remove':
            id: [default_inventory_block]
        
        # 3. Dodaj nowy blok
        - '@add':
            id: acme_cg_inventory_[view_name]
            parentId: [parent_block_id]
            blockType: block
            options:
                vars:
                    inventory: '=data["acme_cg_inventory"].getForProduct(product)'
```

### Krok 2: Utwórz Template Twig
```twig
{% block _acme_cg_inventory_[view_name]_widget %}
    {% set inventory = block.vars.inventory %}
    {# Renderuj badge bazując na inventory.status #}
{% endblock %}
```

### Krok 3: Struktura Katalogów
```
Resources/views/layouts/default/
├── oro_product_frontend_product_view/     # Strona produktu
├── imports/
│   ├── oro_product_list_item/            # Elementy list produktów
│   └── oro_product_grid/                 # Siatki produktów
├── oro_shopping_list_frontend_view/       # Koszyk
├── oro_checkout_frontend_checkout/        # Checkout
├── oro_product_frontend_quick_add/        # Quick order
└── oro_website_search_result_frontend/    # Wyniki wyszukiwania
```

## 📊 Status Badge Classes

| Status | Class | Kolor |
|--------|-------|-------|
| in_stock | `badge--success` | Zielony |
| out_of_stock | `badge--danger` | Czerwony |
| backorder | `badge--warning` | Żółty |
| pre_order | `badge--info` | Niebieski |

## 🔄 Cache & Performance

- Cache provider: `TagAwareCacheInterface`
- TTL: 3600 sekund (1 godzina)
- Invalidacja: Automatyczna przy zmianach w entity
- Tagi: `product_{id}`, `group_{id}`, `website_{id}`

## 🚀 Rozszerzanie na Nowe Widoki

1. **Znajdź layout route** widoku w OroCommerce
2. **Utwórz folder** w `Resources/views/layouts/default/`
3. **Skopiuj wzorzec** z istniejącej implementacji
4. **Dostosuj** parent blocks i structure
5. **Test** z różnymi użytkownikami/grupami

## ⚠️ Ważne Uwagi

- Zawsze używaj `block.vars` do przekazywania danych
- Prefiksuj ID bloków z `acme_cg_inventory_`
- Używaj `badge--xs` dla list, normalny rozmiar dla detail view
- Pamiętaj o tłumaczeniach (`|trans`)
- Testuj z zalogowanym i niezalogowanym użytkownikiem