#!/bin/bash
# Check if old database has different data

echo "Checking old project database..."

# Start old database if needed
cd /home/wojtek/projects/orostore

# Create unique container names for old project
export COMPOSE_PROJECT_NAME=orostore_old

if ! docker compose ps postgres 2>/dev/null | grep -q "running"; then
    echo "Starting old database container with unique name..."
    docker compose up -d postgres
    sleep 5
fi

# Check if sales document table exists
echo "Checking for sales documents in old database..."
docker compose exec -T postgres psql -U oro_db_user -d oro_db -c "
SELECT 
    COUNT(*) as total_documents,
    COUNT(DISTINCT document_number) as unique_documents,
    MIN(document_date) as oldest_date,
    MAX(document_date) as newest_date
FROM acme_sales_document;
" 2>/dev/null || echo "Table acme_sales_document not found in old database"

# Stop the container
echo "Stopping old database container..."
docker compose down

cd /home/wojtek/projects/orostore-fresh