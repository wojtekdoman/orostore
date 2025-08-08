<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Controller;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\SecurityBundle\Attribute\Acl;
use Oro\Bundle\SecurityBundle\Attribute\AclAncestor;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * CRUD controller for Customer Group Inventory management
 */
#[Route(path: '/customer-group-inventory')]
class CustomerGroupInventoryController extends AbstractController
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private TokenAccessorInterface $tokenAccessor
    ) {}

    #[Route(path: '/', name: 'acme_cg_inventory_index')]
    #[Template('@AcmeCustomerGroupInventory/CustomerGroupInventory/index.html.twig')]
    #[AclAncestor('acme_cg_inventory_view')]
    public function indexAction(): array
    {
        return [
            'entity_class' => CustomerGroupInventory::class,
        ];
    }

    #[Route(path: '/create', name: 'acme_cg_inventory_create')]
    #[Template('@AcmeCustomerGroupInventory/CustomerGroupInventory/update.html.twig')]
    #[Acl(
        id: 'acme_cg_inventory_create',
        type: 'entity',
        class: CustomerGroupInventory::class,
        permission: 'CREATE'
    )]
    public function createAction(Request $request): array|RedirectResponse
    {
        $entity = new CustomerGroupInventory();
        
        // Set default organization
        $organization = $this->tokenAccessor->getOrganization();
        if ($organization) {
            $entity->setOrganization($organization);
        }

        return $this->update($entity, $request);
    }

    #[Route(path: '/update/{id}', name: 'acme_cg_inventory_update', requirements: ['id' => '\d+'])]
    #[Template('@AcmeCustomerGroupInventory/CustomerGroupInventory/update.html.twig')]
    #[Acl(
        id: 'acme_cg_inventory_edit',
        type: 'entity',
        class: CustomerGroupInventory::class,
        permission: 'EDIT'
    )]
    public function updateAction(CustomerGroupInventory $entity, Request $request): array|RedirectResponse
    {
        return $this->update($entity, $request);
    }

    #[Route(path: '/delete/{id}', name: 'acme_cg_inventory_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[Acl(
        id: 'acme_cg_inventory_delete',
        type: 'entity',
        class: CustomerGroupInventory::class,
        permission: 'DELETE'
    )]
    public function deleteAction(CustomerGroupInventory $entity): Response
    {
        $em = $this->doctrine->getManagerForClass(CustomerGroupInventory::class);
        $em->remove($entity);
        $em->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Handle create and update actions
     */
    private function update(CustomerGroupInventory $entity, Request $request): array|RedirectResponse
    {
        $form = $this->createForm(CustomerGroupInventoryType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->doctrine->getManagerForClass(CustomerGroupInventory::class);
            $em->persist($entity);
            $em->flush();

            $this->addFlash('success', 'acme.cginventory.saved');

            return $this->redirectToRoute('acme_cg_inventory_index');
        }

        return [
            'entity' => $entity,
            'form' => $form->createView(),
        ];
    }
}