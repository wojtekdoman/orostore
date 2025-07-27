#!/bin/bash
# Script to import sales documents into new database

set -e

echo "=== Importing Sales Documents into New Database ==="

# Check if export files exist
if [ ! -f "/tmp/sales_documents_export.sql" ]; then
    echo "Error: Export file not found. Please run export-sales-documents.sh first."
    exit 1
fi

cd /home/wojtek/projects/orostore-fresh

# Create temporary import script with user mapping
cat > /tmp/import_with_mapping.sql << 'EOF'
-- Temporary tables for mapping
CREATE TEMP TABLE temp_user_mapping (
    old_id INTEGER,
    new_id INTEGER
);

CREATE TEMP TABLE temp_customer_user_mapping (
    old_id INTEGER,
    new_id INTEGER
);

-- Map admin user (usually ID 1 to 1)
INSERT INTO temp_user_mapping (old_id, new_id) VALUES (1, 1);

-- Map customer users by email/username
INSERT INTO temp_customer_user_mapping (old_id, new_id)
SELECT 
    old_cu.id as old_id,
    new_cu.id as new_id
FROM (
    SELECT DISTINCT customer_user_id as id 
    FROM acme_sales_document 
    WHERE customer_user_id IS NOT NULL
) old_ids
JOIN oro_customer_user old_cu ON old_cu.id = old_ids.id
LEFT JOIN oro_customer_user new_cu ON (
    new_cu.email = old_cu.email OR 
    new_cu.username = old_cu.username
)
WHERE new_cu.id IS NOT NULL;

-- Create temporary table for documents
CREATE TEMP TABLE temp_sales_documents AS 
SELECT * FROM acme_sales_document WHERE FALSE;

EOF

# Append the export data
cat /tmp/sales_documents_export.sql >> /tmp/import_with_mapping.sql

# Add mapping update queries
cat >> /tmp/import_with_mapping.sql << 'EOF'

-- Update user references
UPDATE temp_sales_documents tsd
SET 
    customer_user_id = COALESCE(
        (SELECT new_id FROM temp_customer_user_mapping WHERE old_id = tsd.customer_user_id),
        tsd.customer_user_id
    ),
    user_owner_id = COALESCE(
        (SELECT new_id FROM temp_user_mapping WHERE old_id = tsd.user_owner_id),
        tsd.user_owner_id
    );

-- Insert only documents that don't already exist
INSERT INTO acme_sales_document 
SELECT tsd.* 
FROM temp_sales_documents tsd
WHERE NOT EXISTS (
    SELECT 1 FROM acme_sales_document sd 
    WHERE sd.document_number = tsd.document_number 
    AND sd.organization_id = tsd.organization_id
);

-- Show import results
SELECT 
    (SELECT COUNT(*) FROM temp_sales_documents) as total_to_import,
    (SELECT COUNT(*) FROM acme_sales_document) as total_after_import;

EOF

# Execute import
echo "Importing sales documents with user mapping..."
docker compose exec -T pgsql psql -U oro_db_user -d oro_db < /tmp/import_with_mapping.sql

# Clear cache
echo "Clearing cache..."
docker compose exec php-fpm rm -rf var/cache/*
docker compose exec php-fpm bin/console cache:clear

echo "Import completed!"

# Show summary
echo ""
echo "=== Import Summary ==="
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
SELECT 
    COUNT(*) as total_documents,
    COUNT(DISTINCT customer_user_id) as unique_customers,
    COUNT(DISTINCT document_type) as document_types,
    MIN(document_date) as oldest_document,
    MAX(document_date) as newest_document
FROM acme_sales_document;
"