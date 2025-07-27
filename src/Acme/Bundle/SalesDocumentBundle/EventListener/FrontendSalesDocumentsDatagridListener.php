<?php

namespace Acme\Bundle\SalesDocumentBundle\EventListener;

use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FrontendSalesDocumentsDatagridListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $token = $this->tokenStorage->getToken();
        
        if (!$token || !($token->getUser() instanceof CustomerUser)) {
            return;
        }
        
        /** @var CustomerUser $user */
        $user = $token->getUser();
        
        $datasource = $datagrid->getDatasource();
        if ($datasource && method_exists($datasource, 'getQueryBuilder')) {
            $qb = $datasource->getQueryBuilder();
            $qb->andWhere('doc.customerUser = :customer_user')
               ->setParameter('customer_user', $user->getId());
        }
    }
}