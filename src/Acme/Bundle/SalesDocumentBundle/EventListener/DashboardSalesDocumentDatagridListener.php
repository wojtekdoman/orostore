<?php

namespace Acme\Bundle\SalesDocumentBundle\EventListener;

use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DashboardSalesDocumentDatagridListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public function onBuildBefore(BuildBefore $event): void
    {
        $datagrid = $event->getDatagrid();
        $config = $event->getConfig();
        
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }
        
        $user = $token->getUser();
        if (!$user instanceof CustomerUser) {
            return;
        }
        
        // Set parameter for datagrid
        $datagrid->getParameters()->add(['customer_user_id' => $user->getId()]);
    }
}