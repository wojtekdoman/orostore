#!/bin/bash
#
# Complete backup script for OroCommerce - includes database AND attachment files
# This creates a single archive with both database dump and all attachment files
#

# Configuration
BACKUP_DIR="/home/wojtek/projects/orostore/database/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="oro_complete_backup_${TIMESTAMP}"
TEMP_DIR="/tmp/${BACKUP_NAME}"

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

echo -e "${GREEN}=== OroCommerce Complete Backup ===${NC}"
echo "Creating backup: ${BACKUP_NAME}"

# Create temp directory
mkdir -p "${TEMP_DIR}"
mkdir -p "${BACKUP_DIR}"

# Step 1: Dump database
echo -e "\n${YELLOW}Step 1: Dumping database...${NC}"
docker compose exec -T pgsql pg_dump -U ${DB_USER} ${DB_NAME} > "${TEMP_DIR}/database.sql"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database dumped successfully${NC}"
    DB_SIZE=$(du -h "${TEMP_DIR}/database.sql" | cut -f1)
    echo "  Database size: ${DB_SIZE}"
else
    echo -e "${RED}✗ Database dump failed${NC}"
    rm -rf "${TEMP_DIR}"
    exit 1
fi

# Step 2: Copy attachment files
echo -e "\n${YELLOW}Step 2: Copying attachment files...${NC}"
ATTACHMENTS_DIR="var/data/attachments"

if [ -d "${ATTACHMENTS_DIR}" ]; then
    cp -r "${ATTACHMENTS_DIR}" "${TEMP_DIR}/attachments"
    FILE_COUNT=$(find "${TEMP_DIR}/attachments" -type f | wc -l)
    echo -e "${GREEN}✓ Copied ${FILE_COUNT} attachment files${NC}"
else
    echo -e "${YELLOW}⚠ No attachments directory found${NC}"
fi

# Step 3: Create metadata file
echo -e "\n${YELLOW}Step 3: Creating metadata...${NC}"
cat > "${TEMP_DIR}/backup_info.json" << EOF
{
    "backup_date": "$(date -Iseconds)",
    "oro_version": "6.1.x",
    "database": {
        "name": "${DB_NAME}",
        "size": "${DB_SIZE}"
    },
    "attachments": {
        "count": ${FILE_COUNT:-0},
        "directory": "var/data/attachments"
    },
    "restore_instructions": "Use restore-complete-with-files.sh script"
}
EOF
echo -e "${GREEN}✓ Metadata created${NC}"

# Step 4: Create compressed archive
echo -e "\n${YELLOW}Step 4: Creating compressed archive...${NC}"
cd /tmp
tar -czf "${BACKUP_DIR}/${BACKUP_NAME}.tar.gz" "${BACKUP_NAME}"

if [ $? -eq 0 ]; then
    FINAL_SIZE=$(du -h "${BACKUP_DIR}/${BACKUP_NAME}.tar.gz" | cut -f1)
    echo -e "${GREEN}✓ Archive created successfully${NC}"
    echo "  Final backup size: ${FINAL_SIZE}"
    echo "  Location: ${BACKUP_DIR}/${BACKUP_NAME}.tar.gz"
else
    echo -e "${RED}✗ Archive creation failed${NC}"
    rm -rf "${TEMP_DIR}"
    exit 1
fi

# Step 5: Cleanup
echo -e "\n${YELLOW}Step 5: Cleaning up...${NC}"
rm -rf "${TEMP_DIR}"
echo -e "${GREEN}✓ Cleanup complete${NC}"

# Summary
echo -e "\n${GREEN}=== Backup Complete ===${NC}"
echo "Backup file: ${BACKUP_DIR}/${BACKUP_NAME}.tar.gz"
echo "Contains:"
echo "  - Full database dump"
echo "  - ${FILE_COUNT:-0} attachment files"
echo ""
echo "To restore, use: ./scripts/restore-complete-with-files.sh ${BACKUP_NAME}.tar.gz"