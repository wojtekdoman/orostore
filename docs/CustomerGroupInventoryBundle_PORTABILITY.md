# CustomerGroupInventoryBundle - Analiza Przenośności

## ✅ Bundle jest w pełni samowystarczalny

### 1. Brak ingerencji w kod OroCommerce
Bundle **NIE MODYFIKUJE** żadnych plików OroCommerce. Wszystkie pliki znajdują się wyłącznie w:
```
src/Acme/Bundle/CustomerGroupInventoryBundle/
```

### 2. Rejestracja bundle
Bundle rejestruje się sam poprzez mechanizm OroCommerce:
- Plik `Resources/config/oro/bundles.yml` automatycznie rejestruje bundle
- Nie wymaga modyfikacji `AppKernel.php` ani innych plików konfiguracyjnych
- OroKernel automatycznie wykrywa i ładuje bundle

### 3. Integracja z OroCommerce
Bundle integruje się poprzez standardowe mechanizmy OroCommerce:
- **Layout system** - dodaje/usuwa bloki bez modyfikacji core'owych template'ów
- **Services** - używa dependency injection
- **Events** - subskrybuje się do eventów Doctrine
- **Forms** - rozszerza formularze poprzez form extensions
- **Datagrids** - definiuje własne grid'y

## 📦 Instrukcja przenoszenia do innej instalacji

### Krok 1: Skopiuj bundle
```bash
# Skopiuj cały folder bundle do nowej instalacji
cp -r src/Acme/Bundle/CustomerGroupInventoryBundle /path/to/new/orocommerce/src/Acme/Bundle/
```

### Krok 2: Uruchom migracje
```bash
# W nowej instalacji
bin/console oro:migration:load --force
```

### Krok 3: Wyczyść cache
```bash
bin/console cache:clear
```

### Krok 4: (Opcjonalnie) Dodaj dane testowe
```sql
-- Przykładowe override'y dla testów
INSERT INTO acme_cg_inventory (product_id, customer_group_id, website_id, inventory_status, is_active, created_at, updated_at, organization_id, business_unit_owner_id)
VALUES 
  ((SELECT id FROM oro_product WHERE sku = 'YOUR_SKU'), 
   (SELECT id FROM oro_customer_group WHERE name = 'YOUR_GROUP'),
   NULL,
   'out_of_stock',
   true,
   NOW(),
   NOW(),
   1,
   1);
```

## ✅ Wymagania systemowe

Bundle wymaga:
- OroCommerce 6.1+ (Community lub Enterprise Edition)
- PHP 8.4+
- PostgreSQL 15+
- Doctrine ORM z PHP 8 attributes support

## ✅ Kompatybilność z aktualizacjami

### Dlaczego bundle jest bezpieczny przy aktualizacjach OroCommerce:

1. **Brak modyfikacji core'a** - nie nadpisuje żadnych plików OroCommerce
2. **Używa publicznych API** - tylko oficjalne service'y i interface'y
3. **Własny namespace** - `Acme\Bundle\CustomerGroupInventoryBundle`
4. **Standardowe mechanizmy rozszerzeń**:
   - Layout system (dodawanie/usuwanie bloków)
   - Form extensions (nie nadpisuje, tylko rozszerza)
   - Event subscribers (nasłuchuje, nie modyfikuje)

### Co może wymagać dostosowania przy major updates:

1. **Layout structure** - jeśli OroCommerce zmieni strukturę bloków w product view
2. **Service definitions** - jeśli zmienią się nazwy/interface'y service'ów
3. **Doctrine attributes** - jeśli zmieni się składnia (mało prawdopodobne)

## 📋 Checklist przed migracją

- [ ] Sprawdź wersję OroCommerce (min. 6.1)
- [ ] Sprawdź wersję PHP (min. 8.4)
- [ ] Upewnij się, że masz PostgreSQL (nie MySQL)
- [ ] Sprawdź czy istnieją tabele: `oro_product`, `oro_customer_group`, `oro_website`
- [ ] Zweryfikuj uprawnienia do tworzenia tabel w bazie danych

## 🔧 Troubleshooting

### Problem: Bundle nie jest widoczny po instalacji
**Rozwiązanie:**
```bash
rm -rf var/cache/*
bin/console cache:warmup
```

### Problem: Migracje nie wykonują się
**Rozwiązanie:**
```bash
bin/console oro:migration:load --force --show-queries
```

### Problem: Status nie wyświetla się na froncie
**Rozwiązanie:**
1. Sprawdź czy layout jest w folderze `default` (nie `oro`)
2. Upewnij się że cache jest wyczyszczony
3. Sprawdź logi czy CustomerGroupContextResolver rozpoznaje użytkownika

## 📊 Struktura bundle (do skopiowania)

```
CustomerGroupInventoryBundle/
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
    │   ├── controllers.yml
    │   ├── oro/
    │   │   ├── bundles.yml
    │   │   ├── datagrids.yml
    │   │   ├── navigation.yml
    │   │   └── routing.yml
    │   ├── routes.yml
    │   └── services.yaml
    ├── translations/
    │   └── messages.en.yml
    └── views/
        ├── CustomerGroupInventory/
        │   ├── create.html.twig
        │   ├── index.html.twig
        │   └── update.html.twig
        └── layouts/
            └── default/
                └── oro_product_frontend_product_view/
                    ├── layout.yml
                    └── widgets/
                        └── customer_group_inventory.html.twig
```

## ✅ Podsumowanie

Bundle CustomerGroupInventoryBundle jest **w pełni przenośny** i może być bezpiecznie:
1. Skopiowany do innych instalacji OroCommerce 6.1+
2. Aktualizowany wraz z OroCommerce (minimalne ryzyko konfliktów)
3. Używany w środowiskach produkcyjnych
4. Rozszerzany o dodatkowe funkcjonalności

Nie wymaga żadnych modyfikacji w kodzie OroCommerce i używa wyłącznie oficjalnych, publicznych API.