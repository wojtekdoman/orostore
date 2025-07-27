# OroCommerce 6.1 - Szczegółowa instrukcja instalacji

## Spis treści
1. [Wymagania systemowe](#wymagania-systemowe)
2. [Przygotowanie środowiska](#przygotowanie-środowiska)
3. [Instalacja krok po kroku](#instalacja-krok-po-kroku)
4. [Rozwiązywanie problemów](#rozwiązywanie-problemów)
5. [Konfiguracja po instalacji](#konfiguracja-po-instalacji)

## Wymagania systemowe

### Minimalne wymagania
- **System operacyjny**: Linux (Ubuntu/Debian) lub Windows z WSL2
- **Docker**: 20.10+
- **Docker Compose**: 2.0+
- **RAM**: minimum 8GB (zalecane 16GB)
- **Dysk**: minimum 20GB wolnego miejsca

### Wymagania aplikacji
- **PHP**: 8.4
- **PostgreSQL**: 13+
- **Redis**: 7+
- **Node.js**: 22.9.0
- **npm**: 10.8.3

## Przygotowanie środowiska

### 1. Instalacja Docker i Docker Compose

#### Linux/WSL2:
```bash
# Aktualizacja pakietów
sudo apt update

# Instalacja Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Dodanie użytkownika do grupy docker
sudo usermod -aG docker $USER

# Instalacja Docker Compose
sudo apt install docker-compose
```

### 2. Sprawdzenie instalacji
```bash
docker --version
docker compose version
```

## Instalacja krok po kroku

### 1. Pobranie kodu źródłowego

```bash
# Utworzenie katalogu projektu
mkdir -p ~/projects/orostore-fresh
cd ~/projects/orostore-fresh

# Klonowanie repozytorium OroCommerce 6.1
git clone --branch 6.1.0 https://github.com/oroinc/orocommerce-application.git .
```

### 2. Konfiguracja Docker

Projekt zawiera już pliki konfiguracyjne Docker w katalogu `docker/`:
- `docker-compose.yml` - konfiguracja usług
- `docker/php/Dockerfile` - obraz PHP-FPM
- `docker/nginx/default.conf` - konfiguracja Nginx

### 3. Konfiguracja środowiska

Utwórz plik `.env-app.local` z konfiguracją:

```bash
cat > .env-app.local << 'EOF'
ORO_ENV=dev
ORO_SECRET=ThisTokenIsNotSoSecretChangeIt
ORO_DB_DSN=postgresql://oro_db_user:oro_db_pass@pgsql:5432/oro_db?serverVersion=15&charset=UTF-8
ORO_MAILER_DSN=smtp://mailhog:1025
ORO_REDIS_DSN=redis://redis:6379/0
ORO_REDIS_CACHE_DSN=redis://redis:6379/1
ORO_REDIS_DOCTRINE_DSN=redis://redis:6379/2
ORO_REDIS_LAYOUT_DSN=redis://redis:6379/3
ORO_SEARCH_ENGINE_DSN=orm:?prefix=oro_search
ORO_WEBSITE_SEARCH_ENGINE_DSN=orm:?prefix=oro_website_search
ORO_SESSION_DSN=native:
ORO_WEBSOCKET_SERVER_DSN=//0.0.0.0:8080
ORO_WEBSOCKET_FRONTEND_DSN=//*:8080/ws
ORO_WEBSOCKET_BACKEND_DSN=tcp://127.0.0.1:8080
ORO_ENTERPRISE_LICENCE=
ORO_GOOGLE_INTEGRATION_CLIENT_ID=
ORO_GOOGLE_INTEGRATION_CLIENT_SECRET=
ORO_MICROSOFT_365_INTEGRATION_CLIENT_ID=
ORO_MICROSOFT_365_INTEGRATION_CLIENT_SECRET=
ORO_MICROSOFT_365_INTEGRATION_TENANT_ID=
ORO_OAUTH2_REFRESH_TOKEN_LIFETIME=18000
ORO_OAUTH2_ACCESS_TOKEN_LIFETIME=900
ORO_MQ_DSN=dbal:
EOF
```

### 4. Uruchomienie kontenerów Docker

```bash
# Uruchomienie wszystkich usług
docker compose up -d

# Sprawdzenie statusu kontenerów
docker compose ps
```

### 5. Instalacja zależności

```bash
# Instalacja zależności PHP (Composer)
docker compose exec php-fpm composer install --no-interaction

# Instalacja zależności JavaScript (npm)
docker compose exec php-fpm npm install
```

### 6. Instalacja aplikacji OroCommerce

#### WAŻNE: Przed instalacją włącz rozszerzenie UUID w PostgreSQL
```bash
docker compose exec pgsql psql -U oro_db_user -d oro_db -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"
```

#### Instalacja z danymi demonstracyjnymi
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

#### Instalacja bez danych demonstracyjnych
```bash
# Użyj tej samej komendy ale z parametrem --sample-data=n
--sample-data=n
```

### 7. Ładowanie danych demo (jeśli pominięte podczas instalacji)

```bash
docker compose exec php-fpm bin/console oro:migration:data:load --fixtures-type=demo --env=prod
```

### 8. Instalacja i budowanie assetów

```bash
# Instalacja assetów Symfony
docker compose exec php-fpm bin/console oro:assets:install --symlink

# Budowanie assetów frontend (może potrwać kilka minut)
docker compose exec php-fpm npm run build
```

### 9. Utworzenie markera instalacji

```bash
docker compose exec php-fpm touch var/data/installed
```

## Rozwiązywanie problemów

### Problem: "The application is not installed"

**Rozwiązanie:**
```bash
# Upewnij się, że marker instalacji istnieje
docker compose exec php-fpm touch var/data/installed

# Wyczyść cache
docker compose exec php-fpm rm -rf var/cache/*
docker compose exec php-fpm bin/console cache:clear --env=prod

# Zrestartuj kontenery
docker compose restart
```

### Problem: "uuid_generate_v4() does not exist"

**Rozwiązanie:**
```bash
docker compose exec pgsql psql -U oro_db_user -d oro_db -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"
```

### Problem: Brak danych demo

**Rozwiązanie:**
```bash
# Załaduj dane demo ręcznie
docker compose exec php-fpm bin/console oro:migration:data:load --fixtures-type=demo --env=prod
```

### Problem: Błędy uprawnień

**Rozwiązanie:**
```bash
# Napraw uprawnienia
docker compose exec php-fpm chown -R www:www var/cache var/logs var/data public/media
docker compose exec php-fpm chmod -R 777 var/cache var/logs var/data
```

## Konfiguracja po instalacji

### 1. Przełączanie między trybami

#### Tryb produkcyjny (szybszy)
```bash
# Edytuj plik .env-app.local
ORO_ENV=prod

# Wyczyść i odbuduj cache
docker compose exec php-fpm rm -rf var/cache/*
docker compose exec php-fpm bin/console cache:clear --env=prod
docker compose exec php-fpm bin/console cache:warmup --env=prod
```

#### Tryb deweloperski (z debuggerem)
```bash
# Edytuj plik .env-app.local
ORO_ENV=dev

# Wyczyść cache
docker compose exec php-fpm rm -rf var/cache/*
docker compose exec php-fpm bin/console cache:clear
```

### 2. Dostęp do aplikacji

- **Frontend sklepu**: http://localhost/
- **Panel administracyjny**: http://localhost/admin/
- **MailCatcher**: http://localhost:1080/
- **Profiler Symfony** (tylko w trybie dev): http://localhost/_profiler/

### 3. Domyślne dane logowania

- **Login**: admin
- **Hasło**: admin

**WAŻNE**: Zmień hasło administratora po pierwszym logowaniu!

### 4. Komendy konserwacyjne

```bash
# Sprawdzenie statusu aplikacji
docker compose exec php-fpm bin/console oro:check-requirements

# Czyszczenie cache
docker compose exec php-fpm bin/console cache:clear

# Przebudowa indeksów wyszukiwania
docker compose exec php-fpm bin/console oro:search:reindex

# Aktualizacja cen
docker compose exec php-fpm bin/console oro:pricing:build

# Generowanie sitemap
docker compose exec php-fpm bin/console oro:sitemap:generate
```

### 5. Backup bazy danych

```bash
# Utworzenie backupu
docker compose exec pgsql pg_dump -U oro_db_user oro_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Przywrócenie z backupu
docker compose exec -T pgsql psql -U oro_db_user oro_db < backup_20250727_161500.sql
```

## Struktura projektu

```
orostore-fresh/
├── bin/                    # Skrypty wykonywalne (console)
├── config/                 # Konfiguracja Symfony
├── docker/                 # Pliki konfiguracyjne Docker
│   ├── nginx/             # Konfiguracja Nginx
│   └── php/               # Dockerfile dla PHP-FPM
├── public/                 # Katalog publiczny (web root)
│   ├── build/             # Zbudowane assety JS/CSS
│   └── media/             # Pliki mediów
├── src/                    # Kod źródłowy aplikacji
├── templates/              # Szablony Twig
├── translations/           # Pliki tłumaczeń
├── var/                    # Pliki tymczasowe
│   ├── cache/             # Cache aplikacji
│   ├── data/              # Dane aplikacji
│   └── logs/              # Logi aplikacji
├── vendor/                 # Zależności PHP (Composer)
├── docker-compose.yml      # Konfiguracja Docker Compose
├── composer.json           # Zależności PHP
├── package.json           # Zależności JavaScript
└── webpack.config.js      # Konfiguracja Webpack
```

## Wsparcie

- **Dokumentacja oficjalna**: https://doc.oroinc.com/
- **Forum społeczności**: https://forum.oroinc.com/
- **GitHub**: https://github.com/oroinc/orocommerce

---

Ostatnia aktualizacja: 27 lipca 2025