#!/bin/bash

echo "=== Sprawdzanie dokumentów w starej bazie ==="

cd /home/wojtek/projects/orostore

# Start old database
docker compose up -d postgres 2>/dev/null || true
sleep 3

echo ""
echo "Liczba dokumentów w starej bazie:"
docker compose exec -T postgres psql -U oro_db_user -d oro_db -c "
SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT customer_user_id) as unique_customers,
    COUNT(DISTINCT document_type) as document_types
FROM acme_sales_document;
"

echo ""
echo "Dokumenty według typu:"
docker compose exec -T postgres psql -U oro_db_user -d oro_db -c "
SELECT 
    document_type,
    COUNT(*) as count
FROM acme_sales_document
GROUP BY document_type;
"

echo ""
echo "Dokumenty według klienta:"
docker compose exec -T postgres psql -U oro_db_user -d oro_db -c "
SELECT 
    customer_user_id,
    COUNT(*) as count
FROM acme_sales_document
GROUP BY customer_user_id
ORDER BY count DESC;
"

# Stop old database
docker compose down