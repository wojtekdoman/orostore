<?php

namespace Acme\Bundle\SalesDocumentBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Acme\Bundle\SalesDocumentBundle\Migrations\Schema\v1_2\CreateSalesDocumentTable;

/**
 * Installer for AcmeDemoBundle
 */
class AcmeSalesDocumentBundleInstaller implements Installation
{
    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion(): string
    {
        return 'v1_2';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        // Create sales document table
        $migration = new CreateSalesDocumentTable();
        $migration->up($schema, $queries);
    }
}