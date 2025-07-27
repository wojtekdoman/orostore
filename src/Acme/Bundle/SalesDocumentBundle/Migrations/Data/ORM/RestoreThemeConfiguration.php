<?php

namespace Acme\Bundle\SalesDocumentBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\CMSBundle\Entity\ContentWidget;
use Oro\Bundle\ThemeBundle\Entity\ThemeConfiguration;

/**
 * Restores the default theme configuration with original widgets
 */
class RestoreThemeConfiguration extends AbstractFixture
{
    public function load(ObjectManager $manager)
    {
        // Get the default theme configuration
        $themeConfig = $manager->getRepository(ThemeConfiguration::class)->findOneBy([
            'theme' => 'default',
            'organization' => 1
        ]);

        if (!$themeConfig) {
            return;
        }

        // Get widget IDs
        $widgets = $manager->getRepository(ContentWidget::class)->createQueryBuilder('w')
            ->select('w.id, w.name')
            ->where('w.name IN (:names)')
            ->setParameter('names', [
                'users',
                'shopping-lists',
                'open-rfqs',
                'total-orders',
                'my-shopping-lists',
                'my-checkouts',
                'my-latest-orders',
                'open-quotes',
                'latest-rfq'
            ])
            ->getQuery()
            ->getResult();

        // Create a map of widget names to IDs
        $widgetMap = [];
        foreach ($widgets as $widget) {
            $widgetMap[$widget['name']] = $widget['id'];
        }

        // Default configuration array
        $defaultConfig = [
            'customer_user_dashboard__scorecard_widget' => $widgetMap['users'] ?? null,
            'customer_user_dashboard__scorecard_widget_2' => $widgetMap['shopping-lists'] ?? null,
            'customer_user_dashboard__scorecard_widget_3' => $widgetMap['open-rfqs'] ?? null,
            'customer_user_dashboard__scorecard_widget_4' => $widgetMap['total-orders'] ?? null,
            'customer_user_dashboard__content_widget' => $widgetMap['my-shopping-lists'] ?? null,
            'customer_user_dashboard__content_widget_2' => $widgetMap['my-checkouts'] ?? null,
            'customer_user_dashboard__content_widget_3' => $widgetMap['my-latest-orders'] ?? null,
            'customer_user_dashboard__content_widget_4' => $widgetMap['open-quotes'] ?? null,
            'customer_user_dashboard__content_widget_5' => $widgetMap['latest-rfq'] ?? null,
        ];

        // Update theme configuration
        $themeConfig->setConfiguration($defaultConfig);
        $manager->persist($themeConfig);
        $manager->flush();
    }
}