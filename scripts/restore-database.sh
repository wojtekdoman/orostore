#!/bin/bash

# Database restore script for OroCommerce
# This script restores a PostgreSQL database from backup

# Configuration
BACKUP_DIR="database/backups"
DB_USER="oro_db_user"
DB_NAME="oro_db"
DB_CONTAINER="orostore-db"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Change to project root
cd "$PROJECT_ROOT"

# Function to list available backups
list_backups() {
    echo -e "${BLUE}Available backups:${NC}"
    ls -1t "$BACKUP_DIR"/oro_db_backup_*.sql.gz 2>/dev/null | nl -s '. ' | sed 's|.*/||'
}

# Check if Docker container is running
if ! docker ps | grep -q "$DB_CONTAINER"; then
    echo -e "${RED}Error: Database container '$DB_CONTAINER' is not running!${NC}"
    echo "Please start Docker containers with: docker compose up -d"
    exit 1
fi

# Check for backup file argument
if [ $# -eq 0 ]; then
    # No argument provided, show menu
    echo -e "${YELLOW}Database Restore Tool${NC}"
    echo
    list_backups
    echo
    echo -e "${YELLOW}Enter the number of the backup to restore (or 'latest' for the most recent):${NC}"
    read -r selection
    
    if [ "$selection" = "latest" ]; then
        BACKUP_FILE="$BACKUP_DIR/latest.sql.gz"
    else
        BACKUP_FILE=$(ls -1t "$BACKUP_DIR"/oro_db_backup_*.sql.gz 2>/dev/null | sed -n "${selection}p")
    fi
else
    # Backup file provided as argument
    BACKUP_FILE="$1"
fi

# Validate backup file
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}Error: Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

# Get absolute path
BACKUP_FILE=$(realpath "$BACKUP_FILE")
BACKUP_FILENAME=$(basename "$BACKUP_FILE")

echo -e "${YELLOW}Selected backup: $BACKUP_FILENAME${NC}"
echo -e "${RED}WARNING: This will replace ALL data in the database!${NC}"
echo -e "${YELLOW}Are you sure you want to restore from this backup? (yes/N)${NC}"
read -r response

if [ "$response" != "yes" ]; then
    echo -e "${YELLOW}Restore cancelled.${NC}"
    exit 0
fi

echo -e "${YELLOW}Creating safety backup before restore...${NC}"
"$PROJECT_ROOT/scripts/backup-database.sh"

echo -e "${YELLOW}Restoring database from backup...${NC}"

# Drop and recreate database
docker compose exec -T pgsql psql -U "$DB_USER" -d postgres <<EOF
DROP DATABASE IF EXISTS "$DB_NAME";
CREATE DATABASE "$DB_NAME";
EOF

# Enable UUID extension
docker compose exec -T pgsql psql -U "$DB_USER" -d "$DB_NAME" -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"

# Restore from backup
if zcat "$BACKUP_FILE" | docker compose exec -T pgsql psql -U "$DB_USER" -d "$DB_NAME" >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Database restored successfully from: $BACKUP_FILENAME${NC}"
    
    # Clear cache after restore
    echo -e "${YELLOW}Clearing application cache...${NC}"
    docker compose exec php-fpm rm -rf var/cache/*
    
    echo -e "${GREEN}✓ Restore completed!${NC}"
    echo -e "${YELLOW}Note: You may need to restart the application containers.${NC}"
else
    echo -e "${RED}✗ Error restoring database!${NC}"
    exit 1
fi