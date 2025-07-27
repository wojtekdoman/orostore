# OroCommerce 6.1 - Szybki start

## Instalacja w 5 krokach

### 1. Uruchom kontenery Docker
```bash
docker compose up -d
```

### 2. Zainstaluj zależności
```bash
docker compose exec php-fpm composer install
docker compose exec php-fpm npm install
```

### 3. Włącz rozszerzenie UUID w bazie danych
```bash
docker compose exec pgsql psql -U oro_db_user -d oro_db -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"
```

### 4. Zainstaluj OroCommerce z danymi demo
```bash
docker compose exec php-fpm bin/console oro:install \
    --env=prod \
    --timeout=0 \
    --user-name=admin \
    --user-email=admin@example.com \
    --user-firstname=John \
    --user-lastname=Doe \
    --user-password=admin \
    --organization-name="Acme Inc" \
    --application-url="http://localhost/" \
    --formatting-code="en_US" \
    --language="en" \
    --sample-data=y
```

### 5. Jeśli dane demo się nie załadowały, uruchom:
```bash
docker compose exec php-fpm bin/console oro:migration:data:load --fixtures-type=demo --env=prod
```

## Dostęp do aplikacji

- **Sklep**: http://localhost/
- **Panel admina**: http://localhost/admin/
- **Login**: admin
- **Hasło**: admin

## Rozwiązywanie problemów

### Jeśli widzisz "The application is not installed":
```bash
docker compose exec php-fpm touch var/data/installed
docker compose exec php-fpm rm -rf var/cache/*
docker compose restart
```

### Zmiana trybu na deweloperski:
Edytuj plik `.env-app.local` i zmień:
```
ORO_ENV=dev
```

Następnie wyczyść cache:
```bash
docker compose exec php-fpm rm -rf var/cache/*
```

---
Szczegółowa dokumentacja: [INSTALLATION.md](INSTALLATION.md)