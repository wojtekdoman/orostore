# Szybka ściągawka - Migracja Sales Documents

## Ścieżki projektów
```bash
# Nowy projekt (tutaj pracujemy)
cd /home/wojtek/projects/orostore-fresh

# Stary projekt (stąd kopiujemy)
cd /home/wojtek/projects/orostore
```

## Najważniejsze komendy

### Start/Stop Docker
```bash
# Start
docker compose up -d

# Stop
docker compose down

# Status
docker compose ps
```

### Cache i assets
```bash
# Wyczyść cache
docker compose exec php-fpm rm -rf var/cache/*
docker compose exec php-fpm bin/console cache:clear

# Przebuduj assets
docker compose exec php-fpm bin/console oro:assets:install
docker compose exec php-fpm npm run build
```

### Baza danych
```bash
# Backup
./scripts/backup-database.sh

# Restore
./scripts/restore-database.sh

# Migracje
docker compose exec php-fpm bin/console oro:migration:load --force

# Sprawdź tabele
docker compose exec pgsql psql -U oro_db_user -d oro_db -c "\dt"
```

### Git
```bash
# Status
git status

# Commit z automatycznym backupem
git add .
git commit -m "opis zmian"

# Cofnij do czystej instalacji
git checkout main
```

## Adresy aplikacji
- Frontend: http://localhost/
- Admin: http://localhost/admin/
- Login: admin / admin

## W razie problemów

### 1. Aplikacja nie działa
```bash
docker compose restart
docker compose exec php-fpm rm -rf var/cache/*
```

### 2. Błąd 500
```bash
# Sprawdź logi
docker compose logs php-fpm --tail=100
tail -f var/log/prod.log
```

### 3. Cofnij wszystko
```bash
git checkout main
./scripts/restore-database.sh
docker compose restart
```

## Co już zrobione
- ✅ Czysta instalacja OroCommerce 6.1
- ✅ System automatycznych backupów
- ✅ Repozytorium na GitHub
- ✅ Branch feature/sales-documents
- ✅ Dokumentacja

## Co do zrobienia
- ⏳ Skopiować SalesDocumentBundle
- ⏳ Zarejestrować bundle
- ⏳ Uruchomić migracje
- ⏳ Skonfigurować uprawnienia
- ⏳ Dodać widgety dashboard