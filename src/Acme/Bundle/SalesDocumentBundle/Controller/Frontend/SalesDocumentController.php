<?php

namespace Acme\Bundle\SalesDocumentBundle\Controller\Frontend;

use Acme\Bundle\SalesDocumentBundle\Entity\SalesDocument;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\AttachmentBundle\Manager\FileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Oro\Bundle\LayoutBundle\Attribute\Layout;
use Oro\Bundle\SecurityBundle\Attribute\AclAncestor;

class SalesDocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileManager $fileManager
    ) {
    }

    /**
     * Displays list of sales documents
     */
    #[Route('/', name: 'acme_sales_document_frontend_index')]
    #[Layout(vars: ['entity_class'])]
    #[AclAncestor('acme_sales_document_frontend_view')]
    public function indexAction(): array
    {
        return [
            'entity_class' => SalesDocument::class,
        ];
    }

    /**
     * Handles single document download
     */
    #[Route('/download/{id}', name: 'acme_sales_document_frontend_download', requirements: ['id' => '\d+'])]
    #[AclAncestor('acme_sales_document_frontend_download')]
    public function downloadAction(int $id): Response
    {
        // Get current user
        $currentUser = $this->getUser();
        if (!$currentUser instanceof CustomerUser) {
            throw $this->createAccessDeniedException('You must be logged in to access documents.');
        }
        
        // First, let's check if document exists at all
        $document = $this->entityManager->getRepository(SalesDocument::class)->find($id);
        
        if (!$document) {
            throw $this->createNotFoundException('Document not found');
        }
        
        // Now check if it belongs to current user
        $documentUser = $document->getCustomerUser();
        if (!$documentUser || $documentUser->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException(sprintf(
                'Access denied. Document user ID: %s, Current user ID: %s',
                $documentUser ? $documentUser->getId() : 'null',
                $currentUser->getId()
            ));
        }
        
        $file = $document->getFile();
        if (!$file) {
            throw $this->createNotFoundException('Document file not found');
        }
        
        // Get the file content
        $content = $this->fileManager->getContent($file);
        
        // Create response with file content
        $response = new Response($content);
        
        // Set headers for file download
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getOriginalFilename() ?? 'document.pdf'
        );
        
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', $file->getMimeType() ?? 'application/pdf');
        
        return $response;
    }

    /**
     * Show document details page
     */
    #[Route('/details/{id}', name: 'acme_frontend_sales_document_view', requirements: ['id' => '\d+'])]
    #[AclAncestor('acme_sales_document_frontend_view')]
    public function detailsAction(int $id): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof CustomerUser) {
            throw $this->createAccessDeniedException('You must be logged in to access documents.');
        }
        
        $document = $this->entityManager->getRepository(SalesDocument::class)->find($id);
        
        if (!$document) {
            throw $this->createNotFoundException('Document not found');
        }
        
        $documentUser = $document->getCustomerUser();
        if (!$documentUser || $documentUser->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        
        return $this->render('@AcmeSalesDocument/Frontend/SalesDocument/view.html.twig', [
            'salesDocument' => $document
        ]);
    }
    
    /**
     * View single document (opens PDF in new window/tab)
     */
    #[Route('/view/{id}', name: 'acme_sales_document_frontend_view', requirements: ['id' => '\d+'])]
    #[AclAncestor('acme_sales_document_frontend_view')]
    public function viewAction(int $id): Response
    {
        // Get current user
        $currentUser = $this->getUser();
        if (!$currentUser instanceof CustomerUser) {
            throw $this->createAccessDeniedException('You must be logged in to access documents.');
        }
        
        // First, let's check if document exists at all
        $document = $this->entityManager->getRepository(SalesDocument::class)->find($id);
        
        if (!$document) {
            throw $this->createNotFoundException('Document not found');
        }
        
        // Now check if it belongs to current user
        $documentUser = $document->getCustomerUser();
        if (!$documentUser || $documentUser->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        
        $file = $document->getFile();
        if (!$file) {
            throw $this->createNotFoundException('Document file not found');
        }
        
        // Get the file content
        $content = $this->fileManager->getContent($file);
        
        // Create response with file content for inline display
        $response = new Response($content);
        
        // Set headers for inline display (view in browser)
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $file->getOriginalFilename() ?? 'document.pdf'
        );
        
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', $file->getMimeType() ?? 'application/pdf');
        
        return $response;
    }

}