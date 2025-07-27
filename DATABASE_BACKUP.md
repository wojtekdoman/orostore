# System backupów bazy danych

## Opis działania

System automatycznie tworzy backup bazy danych PostgreSQL przy każdym commicie do repozytorium Git. Zapewnia to możliwość przywrócenia stanu bazy danych do dowolnego punktu w historii projektu.

## Komponenty systemu

### 1. Skrypt tworzenia backupu
**Lokalizacja**: `scripts/backup-database.sh`

- Tworzy kompresowany backup bazy danych (format .sql.gz)
- Automatycznie usuwa stare backupy (zachowuje 10 najnowszych)
- Tworzy symlink `latest.sql.gz` do najnowszego backupu

### 2. Skrypt przywracania backupu
**Lokalizacja**: `scripts/restore-database.sh`

- Interaktywne menu wyboru backupu
- Tworzy backup bezpieczeństwa przed przywróceniem
- Automatycznie czyści cache aplikacji po przywróceniu

### 3. Git hook pre-commit
**Lokalizacja**: `.git/hooks/pre-commit`

- Automatycznie uruchamia backup przed każdym commitem
- Możliwość pominięcia backupu w przypadku błędu

## Użytkowanie

### Ręczne tworzenie backupu
```bash
./scripts/backup-database.sh
```

### Przywracanie backupu

#### Interaktywnie (z menu):
```bash
./scripts/restore-database.sh
```

#### Z konkretnego pliku:
```bash
./scripts/restore-database.sh database/backups/oro_db_backup_20250727_185553.sql.gz
```

#### Najnowszy backup:
```bash
./scripts/restore-database.sh database/backups/latest.sql.gz
```

## Lokalizacja backupów

Wszystkie backupy są przechowywane w katalogu:
```
database/backups/
```

Format nazwy pliku:
```
oro_db_backup_YYYYMMDD_HHMMSS.sql.gz
```

## Automatyczne czyszczenie

System automatycznie zachowuje tylko 10 najnowszych backupów, aby nie zajmować zbyt dużo miejsca na dysku. Starsze backupy są automatycznie usuwane.

## Wyłączanie automatycznych backupów

Jeśli chcesz tymczasowo wyłączyć automatyczne backupy przy commitach:

```bash
# Zmień nazwę hooka
mv .git/hooks/pre-commit .git/hooks/pre-commit.disabled

# Aby włączyć ponownie
mv .git/hooks/pre-commit.disabled .git/hooks/pre-commit
```

## Uwagi

1. **Rozmiar backupów**: Każdy backup zajmuje około 1-2 MB (skompresowany)
2. **Czas wykonania**: Backup trwa zazwyczaj 1-3 sekundy
3. **Wymagania**: Docker musi być uruchomiony
4. **Bezpieczeństwo**: Backupy nie są commitowane do repozytorium (są w .gitignore)

## Rozwiązywanie problemów

### Błąd: "Database container 'orostore-db' is not running!"
```bash
# Uruchom kontenery Docker
docker compose up -d
```

### Błąd podczas przywracania
1. Sprawdź czy kontener bazy danych działa
2. Sprawdź czy plik backupu nie jest uszkodzony:
   ```bash
   zcat database/backups/nazwa_backupu.sql.gz | head -20
   ```

### Brak miejsca na dysku
Usuń stare backupy ręcznie:
```bash
# Zachowaj tylko 5 najnowszych
ls -1t database/backups/oro_db_backup_*.sql.gz | tail -n +6 | xargs rm -f
```