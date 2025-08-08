#!/bin/bash
#
# Script to add attachment files to existing SQL backup
# This creates a combined archive with SQL dump and attachment files
#

# Check if SQL backup file is provided
if [ $# -eq 0 ]; then
    echo "Usage: $0 <sql_backup_file>"
    echo "Example: $0 oro_db_backup_with_sales_documents_20250730_155000.sql.gz"
    exit 1
fi

SQL_BACKUP="$1"
BACKUP_DIR="/home/wojtek/projects/orostore/database/backups"
SQL_PATH="${BACKUP_DIR}/${SQL_BACKUP}"

# Check if file exists
if [ ! -f "${SQL_PATH}" ]; then
    echo "Error: SQL backup file not found: ${SQL_PATH}"
    exit 1
fi

# Extract base name without extension
BASE_NAME=$(basename "${SQL_BACKUP}" .sql.gz)
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTPUT_NAME="${BASE_NAME}_with_files_${TIMESTAMP}"
TEMP_DIR="/tmp/${OUTPUT_NAME}"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== Adding Files to SQL Backup ===${NC}"
echo "SQL Backup: ${SQL_BACKUP}"
echo "Output: ${OUTPUT_NAME}.tar.gz"

# Create temp directory structure
mkdir -p "${TEMP_DIR}"

# Step 1: Copy SQL backup
echo -e "\n${YELLOW}Step 1: Copying SQL backup...${NC}"
cp "${SQL_PATH}" "${TEMP_DIR}/database.sql.gz"

# Decompress if needed for the restore script
gunzip -c "${TEMP_DIR}/database.sql.gz" > "${TEMP_DIR}/database.sql"

echo -e "${GREEN}✓ SQL backup copied${NC}"

# Step 2: Copy attachment files
echo -e "\n${YELLOW}Step 2: Copying attachment files...${NC}"
if [ -d "var/data/attachments" ]; then
    cp -r "var/data/attachments" "${TEMP_DIR}/attachments"
    FILE_COUNT=$(find "${TEMP_DIR}/attachments" -type f | wc -l)
    echo -e "${GREEN}✓ Copied ${FILE_COUNT} attachment files${NC}"
    
    # List file types
    echo "  File types included:"
    echo "    - $(find "${TEMP_DIR}/attachments" -name "*.jpg" | wc -l) JPG images"
    echo "    - $(find "${TEMP_DIR}/attachments" -name "*.webp" | wc -l) WebP images"
    echo "    - $(find "${TEMP_DIR}/attachments" -name "*.png" | wc -l) PNG images"
    echo "    - $(find "${TEMP_DIR}/attachments" -name "*.svg" | wc -l) SVG graphics"
else
    echo "Warning: No attachments directory found"
    FILE_COUNT=0
fi

# Step 3: Create restore instructions
echo -e "\n${YELLOW}Step 3: Creating restore instructions...${NC}"
cat > "${TEMP_DIR}/RESTORE_INSTRUCTIONS.txt" << EOF
OroCommerce Complete Backup - Restore Instructions
===================================================

This backup contains:
- Database dump (from ${SQL_BACKUP})
- ${FILE_COUNT} attachment files

To restore this backup:

1. Extract the archive:
   tar -xzf ${OUTPUT_NAME}.tar.gz

2. Restore database:
   gunzip -c database.sql.gz | docker compose exec -T pgsql psql -U oro_db_user -d oro_db

   OR if you have the restore script:
   ./scripts/restore-complete-with-files.sh ${OUTPUT_NAME}.tar.gz

3. Restore attachment files:
   cp -r attachments/* /path/to/orostore/var/data/attachments/

4. Fix permissions:
   docker compose exec php-fpm chown -R www:www var/data/attachments
   docker compose exec php-fpm chmod -R 777 var/data/attachments

5. Clear cache:
   docker compose exec php-fpm bin/console cache:clear --env=prod
   docker compose exec php-fpm bin/console liip:imagine:cache:remove

6. Reindex:
   docker compose exec php-fpm bin/console oro:website-search:reindex --env=prod

Backup created: $(date)
EOF
echo -e "${GREEN}✓ Instructions created${NC}"

# Step 4: Create metadata
cat > "${TEMP_DIR}/backup_info.json" << EOF
{
    "original_sql_backup": "${SQL_BACKUP}",
    "created_date": "$(date -Iseconds)",
    "attachments_count": ${FILE_COUNT},
    "type": "complete_with_files"
}
EOF

# Step 5: Create final archive
echo -e "\n${YELLOW}Step 4: Creating final archive...${NC}"
cd /tmp
tar -czf "${BACKUP_DIR}/${OUTPUT_NAME}.tar.gz" "${OUTPUT_NAME}"

FINAL_SIZE=$(du -h "${BACKUP_DIR}/${OUTPUT_NAME}.tar.gz" | cut -f1)
echo -e "${GREEN}✓ Archive created: ${OUTPUT_NAME}.tar.gz${NC}"
echo "  Size: ${FINAL_SIZE}"

# Cleanup
rm -rf "${TEMP_DIR}"

echo -e "\n${GREEN}=== Complete ===${NC}"
echo "Backup with files created:"
echo "  ${BACKUP_DIR}/${OUTPUT_NAME}.tar.gz"
echo ""
echo "This backup now includes both database and all attachment files!"