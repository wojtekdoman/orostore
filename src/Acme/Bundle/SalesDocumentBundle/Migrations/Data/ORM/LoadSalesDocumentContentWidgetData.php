<?php

namespace Acme\Bundle\SalesDocumentBundle\Migrations\Data\ORM;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\CMSBundle\Entity\ContentWidget;
use Oro\Bundle\CMSBundle\Migrations\Data\AbstractLoadContentWidgetData;

/**
 * Loads sales documents content widget data for dashboard
 */
class LoadSalesDocumentContentWidgetData extends AbstractLoadContentWidgetData
{
    public function getVersion(): string
    {
        return '1.1';
    }

    #[\Override]
    protected function getFilePaths(): string
    {
        return $this->getFilePathsFromLocator('@AcmeSalesDocumentBundle/Migrations/Data/ORM/data/content_widgets.yml');
    }

    #[\Override]
    protected function updateContentWidget(ObjectManager $manager, ContentWidget $contentWidget, array $row): void
    {
    }

    #[\Override]
    protected function getFrontendTheme(): ?string
    {
        return null;
    }
}