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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * CRUD controller for Customer Group Inventory management
 */
#[Route(path: '/customer-group-inventory')]
class CustomerGroupInventoryController extends AbstractController
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private TokenAccessorInterface $tokenAccessor,
        private ?CustomerGroupInventoryProvider $inventoryProvider = null
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

    /**
     * AJAX endpoint to check inventory status for a product SKU
     */
    #[Route(path: '/check', name: 'acme_customer_group_inventory_check', methods: ['GET'])]
    public function checkInventoryAction(Request $request): JsonResponse
    {
        $sku = $request->query->get('sku');
        if (!$sku) {
            return new JsonResponse(['error' => 'SKU is required'], Response::HTTP_BAD_REQUEST);
        }

        // Find product by SKU
        $productRepo = $this->doctrine->getRepository(Product::class);
        $product = $productRepo->findOneBySku($sku);
        
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        // Get inventory status for current customer group
        if ($this->inventoryProvider) {
            $inventory = $this->inventoryProvider->getResolvedInventory($product);
            
            return new JsonResponse([
                'sku' => $sku,
                'status' => $inventory->status,
                'quantity' => $inventory->quantity,
                'is_available' => $inventory->isAvailable(),
                'overridden_by_group' => $inventory->overriddenByGroup,
                'group_name' => $inventory->groupName
            ]);
        }

        // Fallback if provider not available
        return new JsonResponse([
            'sku' => $sku,
            'status' => 'in_stock',
            'is_available' => true
        ]);
    }
}