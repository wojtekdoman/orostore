#!/bin/bash
# Script to export sales documents from old database

set -e

echo "=== Exporting Sales Documents from Old Database ==="

# Navigate to old project
cd /home/wojtek/projects/orostore

# Check if container is running
if ! docker compose ps pgsql | grep -q "running"; then
    echo "Starting old database container..."
    docker compose up -d pgsql
    sleep 5
fi

# Export sales documents data
echo "Exporting sales documents..."
docker compose exec -T pgsql pg_dump -U oro_db_user -d oro_db \
    --table=acme_sales_document \
    --data-only \
    --column-inserts \
    > /tmp/sales_documents_export.sql

# Export related attachments
echo "Exporting file attachments info..."
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
COPY (
    SELECT af.* 
    FROM oro_attachment_file af
    WHERE af.id IN (SELECT DISTINCT file_id FROM acme_sales_document WHERE file_id IS NOT NULL)
) TO STDOUT WITH CSV HEADER;
" > /tmp/sales_documents_files.csv

# Count records
DOC_COUNT=$(docker compose exec -T pgsql psql -U oro_db_user -d oro_db -t -c "SELECT COUNT(*) FROM acme_sales_document")
echo "Found $DOC_COUNT sales documents to export"

# Get user mappings
echo "Exporting user mappings..."
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
COPY (
    SELECT DISTINCT cu.id as old_customer_user_id, cu.username, cu.email, c.name as customer_name
    FROM oro_customer_user cu
    LEFT JOIN oro_customer c ON cu.customer_id = c.id
    WHERE cu.id IN (SELECT DISTINCT customer_user_id FROM acme_sales_document WHERE customer_user_id IS NOT NULL)
) TO STDOUT WITH CSV HEADER;
" > /tmp/customer_user_mapping.csv

docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
COPY (
    SELECT DISTINCT u.id as old_user_id, u.username, u.email
    FROM oro_user u
    WHERE u.id IN (SELECT DISTINCT user_owner_id FROM acme_sales_document WHERE user_owner_id IS NOT NULL)
) TO STDOUT WITH CSV HEADER;
" > /tmp/user_mapping.csv

echo "Export completed!"
echo "Files created:"
echo "  - /tmp/sales_documents_export.sql"
echo "  - /tmp/sales_documents_files.csv"
echo "  - /tmp/customer_user_mapping.csv"
echo "  - /tmp/user_mapping.csv"