<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class CreateCustomerGroupInventoryTable implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        if ($schema->hasTable('acme_cg_inventory')) {
            return;
        }

        $table = $schema->createTable('acme_cg_inventory');
        
        // Primary key
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        
        // Foreign keys
        $table->addColumn('product_id', 'integer', ['notnull' => true]);
        $table->addColumn('customer_group_id', 'integer', ['notnull' => true]);
        $table->addColumn('website_id', 'integer', ['notnull' => false]);
        $table->addColumn('organization_id', 'integer', ['notnull' => true]);
        
        // Data columns
        $table->addColumn('quantity', 'decimal', [
            'precision' => 20, 
            'scale' => 6, 
            'default' => 0
        ]);
        $table->addColumn('inventory_status', 'string', [
            'length' => 32, 
            'notnull' => true,
            'default' => 'in_stock'
        ]);
        $table->addColumn('is_active', 'boolean', [
            'default' => true
        ]);
        
        // Timestamps
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        
        // Set primary key
        $table->setPrimaryKey(['id']);
        
        // Add unique constraint
        $table->addUniqueIndex(
            ['product_id', 'customer_group_id', 'website_id'], 
            'uniq_acme_cgi_pcgws'
        );
        
        // Add indexes for performance
        $table->addIndex(['product_id'], 'idx_acme_cgi_product');
        $table->addIndex(['customer_group_id'], 'idx_acme_cgi_cg');
        $table->addIndex(['website_id'], 'idx_acme_cgi_ws');
        $table->addIndex(['organization_id'], 'idx_acme_cgi_org');
        $table->addIndex(['is_active'], 'idx_acme_cgi_active');
        $table->addIndex(['inventory_status'], 'idx_acme_cgi_status');
        
        // Add foreign key constraints
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_product'),
            ['product_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_customer_group'),
            ['customer_group_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_website'),
            ['website_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }
}