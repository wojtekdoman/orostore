<?php

namespace Acme\Bundle\SalesDocumentBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddDueDateAndAmountPaidFields implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('acme_sales_document');
        
        // Add due_date field
        $table->addColumn('due_date', 'date', ['notnull' => false]);
        
        // Add amount_paid field
        $table->addColumn('amount_paid', 'decimal', [
            'notnull' => false,
            'precision' => 19,
            'scale' => 4
        ]);
    }
}