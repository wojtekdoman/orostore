<?php

namespace Acme\Bundle\SalesDocumentBundle\EventListener;

use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FrontendSalesDocumentDatagridListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $datasource = $datagrid->getDatasource();
        
        if (!$datasource) {
            return;
        }
        
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }
        
        $user = $token->getUser();
        if (!$user instanceof CustomerUser) {
            return;
        }
        
        // Add filter by current customer user
        $qb = $datasource->getQueryBuilder();
        $qb->andWhere('doc.customerUser = :current_user')
           ->setParameter('current_user', $user);
    }
}