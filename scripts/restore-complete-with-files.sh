#!/bin/bash
#
# Complete restore script for OroCommerce - restores database AND attachment files
# Usage: ./restore-complete-with-files.sh backup_file.tar.gz
#

# Check arguments
if [ $# -eq 0 ]; then
    echo "Usage: $0 <backup_file.tar.gz>"
    echo "Example: $0 oro_complete_backup_20250808_120000.tar.gz"
    exit 1
fi

BACKUP_FILE="$1"
BACKUP_DIR="/home/wojtek/projects/orostore/database/backups"
FULL_PATH="${BACKUP_DIR}/${BACKUP_FILE}"
TEMP_DIR="/tmp/oro_restore_$$"

# Database credentials
DB_HOST="172.19.64.1"
DB_PORT="1433"
DB_NAME="oro_db"
DB_USER="oro_db_user"
DB_PASS="oro_db_pass"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== OroCommerce Complete Restore ===${NC}"
echo "Restoring from: ${BACKUP_FILE}"

# Check if backup file exists
if [ ! -f "${FULL_PATH}" ]; then
    echo -e "${RED}✗ Backup file not found: ${FULL_PATH}${NC}"
    exit 1
fi

# Create temp directory
mkdir -p "${TEMP_DIR}"

# Step 1: Extract backup
echo -e "\n${YELLOW}Step 1: Extracting backup...${NC}"
tar -xzf "${FULL_PATH}" -C "${TEMP_DIR}"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Backup extracted successfully${NC}"
    BACKUP_NAME=$(ls "${TEMP_DIR}" | head -1)
    EXTRACT_DIR="${TEMP_DIR}/${BACKUP_NAME}"
else
    echo -e "${RED}✗ Extraction failed${NC}"
    rm -rf "${TEMP_DIR}"
    exit 1
fi

# Display backup info
if [ -f "${EXTRACT_DIR}/backup_info.json" ]; then
    echo -e "\n${YELLOW}Backup Information:${NC}"
    cat "${EXTRACT_DIR}/backup_info.json" | python3 -m json.tool 2>/dev/null || cat "${EXTRACT_DIR}/backup_info.json"
fi

# Confirmation
echo -e "\n${YELLOW}WARNING: This will replace the current database and attachment files!${NC}"
read -p "Continue? (yes/no): " CONFIRM
if [ "${CONFIRM}" != "yes" ]; then
    echo "Restore cancelled."
    rm -rf "${TEMP_DIR}"
    exit 0
fi

# Step 2: Stop application (optional - preserve cache)
echo -e "\n${YELLOW}Step 2: Clearing application cache...${NC}"
docker compose exec php-fpm rm -rf var/cache/* 2>/dev/null || echo "  Cache clearing skipped"

# Step 3: Restore database
echo -e "\n${YELLOW}Step 3: Restoring database...${NC}"
if [ -f "${EXTRACT_DIR}/database.sql" ]; then
    # Drop and recreate database
    docker compose exec -T pgsql psql -U postgres << EOF
DROP DATABASE IF EXISTS ${DB_NAME};
CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};
EOF
    
    # Enable UUID extension
    docker compose exec -T pgsql psql -U ${DB_USER} -d ${DB_NAME} -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"
    
    # Restore database
    docker compose exec -T pgsql psql -U ${DB_USER} -d ${DB_NAME} < "${EXTRACT_DIR}/database.sql"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Database restored successfully${NC}"
    else
        echo -e "${RED}✗ Database restore failed${NC}"
        rm -rf "${TEMP_DIR}"
        exit 1
    fi
else
    echo -e "${YELLOW}⚠ No database dump found in backup${NC}"
fi

# Step 4: Restore attachment files
echo -e "\n${YELLOW}Step 4: Restoring attachment files...${NC}"
if [ -d "${EXTRACT_DIR}/attachments" ]; then
    # Create attachments directory if it doesn't exist
    mkdir -p var/data/attachments
    
    # Clear existing attachments (optional)
    read -p "Remove existing attachment files? (yes/no): " REMOVE_EXISTING
    if [ "${REMOVE_EXISTING}" == "yes" ]; then
        rm -rf var/data/attachments/*
        echo "  Existing files removed"
    fi
    
    # Copy attachments
    cp -r "${EXTRACT_DIR}/attachments/"* var/data/attachments/
    
    # Fix permissions
    docker compose exec php-fpm chown -R www:www var/data/attachments
    docker compose exec php-fpm chmod -R 777 var/data/attachments
    
    FILE_COUNT=$(find var/data/attachments -type f | wc -l)
    echo -e "${GREEN}✓ Restored ${FILE_COUNT} attachment files${NC}"
else
    echo -e "${YELLOW}⚠ No attachments found in backup${NC}"
fi

# Step 5: Clear caches and reindex
echo -e "\n${YELLOW}Step 5: Post-restore tasks...${NC}"

# Clear Symfony cache
docker compose exec php-fpm bin/console cache:clear --env=prod
echo "  ✓ Cache cleared"

# Clear image cache
docker compose exec php-fpm bin/console liip:imagine:cache:remove 2>/dev/null || echo "  Image cache clearing skipped"

# Reindex search
docker compose exec php-fpm bin/console oro:website-search:reindex --env=prod
echo "  ✓ Search reindexed"

# Create installed marker
docker compose exec php-fpm touch var/data/installed
echo "  ✓ Installation marker created"

# Step 6: Cleanup
echo -e "\n${YELLOW}Step 6: Cleaning up...${NC}"
rm -rf "${TEMP_DIR}"
echo -e "${GREEN}✓ Cleanup complete${NC}"

# Summary
echo -e "\n${GREEN}=== Restore Complete ===${NC}"
echo "Restored:"
echo "  - Database: ${DB_NAME}"
echo "  - Attachment files: ${FILE_COUNT:-0}"
echo ""
echo "Application URL: http://localhost:81"
echo "Admin URL: http://localhost:81/admin"
echo ""
echo -e "${YELLOW}Note: You may need to restart Docker containers:${NC}"
echo "  docker compose restart"