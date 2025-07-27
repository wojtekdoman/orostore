<?php

namespace Acme\Bundle\SalesDocumentBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddOwnerFieldToSalesDocument implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('acme_sales_document');
        
        // Add owner field
        $table->addColumn('user_owner_id', 'integer', ['notnull' => false]);
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_owner_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $table->addIndex(['user_owner_id'], 'IDX_SALES_DOC_USER_OWNER');
    }
}