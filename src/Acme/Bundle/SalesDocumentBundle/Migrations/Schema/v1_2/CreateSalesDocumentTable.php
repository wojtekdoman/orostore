<?php

namespace Acme\Bundle\SalesDocumentBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Creates sales_document table for storing independent sales documents from ERP
 */
class CreateSalesDocumentTable implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->createSalesDocumentTable($schema);
        $this->addSalesDocumentForeignKeys($schema);
    }

    /**
     * Create acme_sales_document table
     */
    protected function createSalesDocumentTable(Schema $schema): void
    {
        $table = $schema->createTable('acme_sales_document');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('customer_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('file_id', 'integer', ['notnull' => true]);
        $table->addColumn('organization_id', 'integer', ['notnull' => false]);
        $table->addColumn('document_number', 'string', ['length' => 100]);
        $table->addColumn('document_type', 'string', ['length' => 50, 'default' => 'invoice']);
        $table->addColumn('document_date', 'date', ['notnull' => false]);
        $table->addColumn('amount', 'decimal', ['precision' => 19, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('currency', 'string', ['length' => 3, 'notnull' => false]);
        $table->addColumn('erp_id', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime');
        
        $table->setPrimaryKey(['id']);
        $table->addIndex(['customer_user_id'], 'idx_sales_doc_customer_user');
        $table->addIndex(['document_number'], 'idx_sales_doc_number');
        $table->addIndex(['document_date'], 'idx_sales_doc_date');
        $table->addIndex(['erp_id'], 'idx_sales_doc_erp_id');
        $table->addUniqueIndex(['document_number', 'organization_id'], 'uniq_sales_doc_number_org');
    }

    /**
     * Add acme_sales_document foreign keys
     */
    protected function addSalesDocumentForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('acme_sales_document');
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_customer_user'),
            ['customer_user_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_attachment_file'),
            ['file_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'SET NULL', 'onUpdate' => null]
        );
    }
}