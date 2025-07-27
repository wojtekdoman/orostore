#!/bin/bash

# Script to migrate sales documents from old database to new database

set -e

echo "=== Migrating Sales Documents from Old to New Database ==="

# Export sales documents from old database
echo "Exporting sales documents from old database..."

# Create export directory
mkdir -p /tmp/sales_migration

# Export from old database (orostore project)
cd /home/wojtek/projects/orostore

# Start old database if needed
docker compose up -d postgres 2>/dev/null || true
sleep 3

# Export sales documents with customer user mapping
docker compose exec -T postgres pg_dump -U oro_db_user -d oro_db \
    --table=acme_sales_document \
    --data-only \
    --column-inserts \
    > /tmp/sales_migration/sales_documents.sql

# Get count of documents
DOC_COUNT=$(docker compose exec -T postgres psql -U oro_db_user -d oro_db -t -c "SELECT COUNT(*) FROM acme_sales_document" | tr -d ' ')
echo "Found $DOC_COUNT sales documents to migrate"

# Export file IDs that we need
docker compose exec -T postgres psql -U oro_db_user -d oro_db -t -c "
SELECT DISTINCT file_id FROM acme_sales_document WHERE file_id IS NOT NULL
" > /tmp/sales_migration/file_ids.txt

# Export file records
docker compose exec -T postgres psql -U oro_db_user -d oro_db -c "
COPY (
    SELECT * FROM oro_attachment_file 
    WHERE id IN (SELECT DISTINCT file_id FROM acme_sales_document WHERE file_id IS NOT NULL)
) TO STDOUT WITH CSV HEADER;
" > /tmp/sales_migration/attachment_files.csv

# Stop old database
docker compose down

# Now import to new database
cd /home/wojtek/projects/orostore-fresh

echo "Importing sales documents to new database..."

# First check if we need to create any missing files
docker compose exec -T pgsql psql -U oro_db_user -d oro_db < /tmp/sales_migration/sales_documents.sql

echo "Import completed!"

# Show summary
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
SELECT 
    cu.username as customer,
    COUNT(*) as document_count,
    string_agg(DISTINCT sd.document_type, ', ') as types
FROM acme_sales_document sd
JOIN oro_customer_user cu ON sd.customer_user_id = cu.id
GROUP BY cu.username
ORDER BY document_count DESC;
"

echo ""
echo "=== File Migration ==="
echo "Files need to be copied from:"
echo "  /home/wojtek/projects/orostore/var/data/attachments/"
echo "To:"
echo "  /home/wojtek/projects/orostore-fresh/var/data/attachments/"