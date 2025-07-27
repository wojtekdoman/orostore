# Plan migracji funkcjonalności Sales Documents

## Stan projektu
- **Nowy projekt**: `/home/wojtek/projects/orostore-fresh/`
- **Stary projekt**: `/home/wojtek/projects/orostore/`
- **Branch roboczy**: `feature/sales-documents`
- **GitHub**: https://github.com/wojtekdoman/orocommerce-fresh.git

## Funkcjonalności do przeniesienia

### 1. Sales Documents Bundle
**Lokalizacja w starym projekcie**: `/home/wojtek/projects/orostore/src/Acme/Bundle/SalesDocumentBundle/`

### 2. Uprawnienia (ACL)
- `view_sales_document` - przeglądanie dokumentów sprzedażowych
- `download_file` - pobieranie plików

### 3. Widgety dashboard
- `my_sales_documents` - lista dokumentów użytkownika
- `unpaid_invoices_grid` - grid z niezapłaconymi fakturami

## Plan migracji krok po kroku

### ETAP 1: Analiza i przygotowanie
- [ ] 1.1. Sprawdzić historię commitów w starym projekcie
  ```bash
  cd /home/wojtek/projects/orostore
  git log --oneline --grep="sales" --grep="document" --grep="invoice"
  ```

- [ ] 1.2. Zidentyfikować tabele w bazie danych
  ```bash
  docker compose exec pgsql psql -U oro_db_user -d oro_db -c "\dt *sales*"
  docker compose exec pgsql psql -U oro_db_user -d oro_db -c "\dt *document*"
  ```

- [ ] 1.3. Sprawdzić zależności bundle'a
  - Jakie inne bundle są wymagane?
  - Czy używa specyficznych bibliotek?

### ETAP 2: Kopiowanie podstawowych plików

- [ ] 2.1. Skopiować cały bundle
  ```bash
  # W nowym projekcie
  cd /home/wojtek/projects/orostore-fresh
  cp -r /home/wojtek/projects/orostore/src/Acme /home/wojtek/projects/orostore-fresh/src/
  ```

- [ ] 2.2. Sprawdzić strukturę bundle'a
  ```
  src/Acme/Bundle/SalesDocumentBundle/
  ├── AcmeSalesDocumentBundle.php
  ├── Controller/
  │   ├── Api/
  │   └── Frontend/
  ├── Entity/
  │   └── SalesDocument.php
  ├── Repository/
  │   └── SalesDocumentRepository.php
  ├── ContentWidget/
  │   └── Provider/
  ├── Migrations/
  │   ├── Schema/
  │   └── Data/
  ├── Resources/
  │   ├── config/
  │   │   ├── oro/
  │   │   │   ├── acls.yml
  │   │   │   ├── api.yml
  │   │   │   ├── datagrids.yml
  │   │   │   ├── navigation.yml
  │   │   │   └── routing.yml
  │   │   └── services.yml
  │   ├── translations/
  │   └── views/
  └── Tests/
  ```

### ETAP 3: Migracje bazy danych

- [ ] 3.1. Zidentyfikować potrzebne migracje
  ```bash
  # Lista migracji w bundle
  ls -la src/Acme/Bundle/SalesDocumentBundle/Migrations/Schema/
  ```

- [ ] 3.2. Sprawdzić czy tabele już istnieją
  ```bash
  docker compose exec pgsql psql -U oro_db_user -d oro_db -c "\d acme_sales_document"
  ```

- [ ] 3.3. Uruchomić migracje
  ```bash
  docker compose exec php-fpm bin/console oro:migration:load --force --bundle=AcmeSalesDocumentBundle
  ```

### ETAP 4: Rejestracja bundle'a

- [ ] 4.1. Dodać bundle do `config/bundles.php`
  ```php
  return [
      // ... inne bundle
      Acme\Bundle\SalesDocumentBundle\AcmeSalesDocumentBundle::class => ['all' => true],
  ];
  ```

- [ ] 4.2. Wyczyścić cache
  ```bash
  docker compose exec php-fpm rm -rf var/cache/*
  docker compose exec php-fpm bin/console cache:clear
  ```

### ETAP 5: Konfiguracja uprawnień (ACL)

- [ ] 5.1. Sprawdzić plik `Resources/config/oro/acls.yml`
- [ ] 5.2. Załadować uprawnienia
  ```bash
  docker compose exec php-fpm bin/console oro:security:configuration:load-permissions
  ```

### ETAP 6: Routing i nawigacja

- [ ] 6.1. Sprawdzić routing w `Resources/config/oro/routing.yml`
- [ ] 6.2. Sprawdzić menu w `Resources/config/oro/navigation.yml`
- [ ] 6.3. Przebudować routing
  ```bash
  docker compose exec php-fpm bin/console fos:js-routing:dump
  ```

### ETAP 7: Widgety dashboard

- [ ] 7.1. Sprawdzić definicje widgetów w:
  - `Resources/config/oro/datagrids.yml`
  - `ContentWidget/Provider/`

- [ ] 7.2. Załadować dane widgetów
  ```bash
  docker compose exec php-fpm bin/console oro:migration:data:load --fixtures-type=main --bundles=AcmeSalesDocumentBundle
  ```

### ETAP 8: Assets i frontend

- [ ] 8.1. Sprawdzić czy są jakieś pliki JS/CSS
- [ ] 8.2. Zainstalować assets
  ```bash
  docker compose exec php-fpm bin/console oro:assets:install
  ```
- [ ] 8.3. Przebudować assets
  ```bash
  docker compose exec php-fpm npm run build
  ```

### ETAP 9: Tłumaczenia

- [ ] 9.1. Sprawdzić pliki tłumaczeń
  ```bash
  ls -la src/Acme/Bundle/SalesDocumentBundle/Resources/translations/
  ```

- [ ] 9.2. Załadować tłumaczenia
  ```bash
  docker compose exec php-fpm bin/console oro:translation:load
  ```

### ETAP 10: Testowanie

- [ ] 10.1. Sprawdzić czy bundle się ładuje
  ```bash
  docker compose exec php-fpm bin/console debug:container | grep sales
  ```

- [ ] 10.2. Testować frontend
  - Zalogować się do panelu admina
  - Sprawdzić menu Sales Documents
  - Sprawdzić widgety na dashboard

- [ ] 10.3. Testować uprawnienia
  - Sprawdzić role w System > User Management > Roles
  - Przypisać uprawnienia do roli

### ETAP 11: Rozwiązywanie problemów

Częste problemy i rozwiązania:

1. **Błąd: Class not found**
   - Sprawdzić namespace w plikach
   - Sprawdzić autoloading: `composer dump-autoload`

2. **Błąd: Table does not exist**
   - Uruchomić migracje
   - Sprawdzić nazwę tabeli w Entity

3. **Błąd: Route not found**
   - Wyczyścić cache
   - Sprawdzić routing.yml
   - Przebudować routing

4. **Widget nie pokazuje się**
   - Sprawdzić konfigurację w datagrids.yml
   - Sprawdzić uprawnienia
   - Sprawdzić czy provider jest zarejestrowany

## Komendy pomocnicze

### Sprawdzenie stanu
```bash
# Status git
git status

# Które pliki zostały skopiowane
find src/Acme -type f | wc -l

# Sprawdzenie logów
docker compose logs php-fpm --tail=50
```

### Backup przed zmianami
```bash
# Ręczny backup
./scripts/backup-database.sh

# Przywrócenie w razie problemów
./scripts/restore-database.sh
```

### Cofnięcie zmian
```bash
# Cofnięcie do czystej instalacji
git checkout main
docker compose exec php-fpm rm -rf var/cache/*
./scripts/restore-database.sh database/backups/[backup_przed_migracją].sql.gz
```

## Kontynuacja po przerwie

Jeśli sesja zostanie przerwana:

1. **Sprawdź gdzie jesteś**:
   ```bash
   cd /home/wojtek/projects/orostore-fresh
   git status
   git log --oneline -5
   ```

2. **Sprawdź co już zostało zrobione**:
   - Czy bundle jest skopiowany? `ls -la src/Acme/`
   - Czy jest zarejestrowany? `grep Acme config/bundles.php`
   - Czy tabele istnieją? `docker compose exec pgsql psql -U oro_db_user -d oro_db -c "\dt acme_*"`

3. **Kontynuuj od miejsca gdzie skończyłeś** używając tej listy kontrolnej

## Notatki dodatkowe

- Stary projekt ma problemy z theme configuration (widget ID 16)
- Unpaid invoices scorecard powodował błędy - może wymagać poprawek
- Dashboard w starym projekcie przestał działać - trzeba uważać przy migracji

---
Ostatnia aktualizacja: 27 lipca 2025