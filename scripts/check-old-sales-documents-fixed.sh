#!/bin/bash

echo "=== Sprawdzanie dokumentów w starej bazie ==="

cd /home/wojtek/projects/orostore

# Start old database
docker compose up -d pgsql 2>/dev/null || true
sleep 5

echo ""
echo "Liczba dokumentów w starej bazie:"
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT customer_user_id) as unique_customers,
    COUNT(DISTINCT document_type) as document_types
FROM acme_sales_document;
" 2>&1 || echo "Tabela acme_sales_document nie istnieje w starej bazie"

echo ""
echo "Dokumenty według typu:"
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
SELECT 
    document_type,
    COUNT(*) as count
FROM acme_sales_document
GROUP BY document_type;
" 2>&1 || true

echo ""
echo "Dokumenty według klienta:"
docker compose exec -T pgsql psql -U oro_db_user -d oro_db -c "
SELECT 
    customer_user_id,
    COUNT(*) as count
FROM acme_sales_document
GROUP BY customer_user_id
ORDER BY count DESC
LIMIT 10;
" 2>&1 || true

# Stop old database
docker compose down