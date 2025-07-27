#!/bin/bash

# Database backup script for OroCommerce
# This script creates a backup of the PostgreSQL database

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
NC='\033[0m' # No Color

# Change to project root
cd "$PROJECT_ROOT"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/oro_db_backup_${TIMESTAMP}.sql"
BACKUP_FILE_GZ="${BACKUP_FILE}.gz"

echo -e "${YELLOW}Creating database backup...${NC}"

# Check if Docker container is running
if ! docker ps | grep -q "$DB_CONTAINER"; then
    echo -e "${RED}Error: Database container '$DB_CONTAINER' is not running!${NC}"
    echo "Please start Docker containers with: docker compose up -d"
    exit 1
fi

# Create backup
if docker compose exec -T pgsql pg_dump -U "$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then
    # Compress the backup
    gzip "$BACKUP_FILE"
    
    # Get file size
    SIZE=$(du -h "$BACKUP_FILE_GZ" | cut -f1)
    
    echo -e "${GREEN}✓ Database backup created successfully!${NC}"
    echo "  File: $BACKUP_FILE_GZ"
    echo "  Size: $SIZE"
    
    # Keep only last 10 backups to save space
    BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/oro_db_backup_*.sql.gz 2>/dev/null | wc -l)
    if [ "$BACKUP_COUNT" -gt 10 ]; then
        echo -e "${YELLOW}Cleaning old backups (keeping last 10)...${NC}"
        ls -1t "$BACKUP_DIR"/oro_db_backup_*.sql.gz | tail -n +11 | xargs rm -f
    fi
    
    # Create a symlink to the latest backup
    ln -sf "$BACKUP_FILE_GZ" "$BACKUP_DIR/latest.sql.gz"
    
    exit 0
else
    echo -e "${RED}✗ Error creating database backup!${NC}"
    rm -f "$BACKUP_FILE" 2>/dev/null
    exit 1
fi