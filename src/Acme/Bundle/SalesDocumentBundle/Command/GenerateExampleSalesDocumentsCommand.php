<?php

namespace Acme\Bundle\SalesDocumentBundle\Command;

use Acme\Bundle\SalesDocumentBundle\Entity\SalesDocument;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\AttachmentBundle\Entity\File as AttachmentFile;
use Oro\Bundle\AttachmentBundle\Manager\FileManager;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates example sales documents for testing
 */
class GenerateExampleSalesDocumentsCommand extends Command
{
    protected static $defaultName = 'acme:generate-example-sales-documents';

    public function __construct(
        private EntityManagerInterface $em,
        private FileManager $fileManager,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates example sales documents (invoices, credit notes, etc.) for testing')
            ->setHelp('This command creates sample PDF documents as sales documents for testing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating Example Sales Documents');

        // Get organization
        $organization = $this->em->getRepository(Organization::class)->findOneBy([]);
        if (!$organization) {
            $io->error('No organization found');
            return Command::FAILURE;
        }

        // Get customer users
        $customerUsers = $this->em->getRepository(CustomerUser::class)
            ->createQueryBuilder('cu')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        if (empty($customerUsers)) {
            $io->warning('No customer users found. Please create some customer users first.');
            return Command::SUCCESS;
        }

        $fs = new Filesystem();
        $tempDir = sys_get_temp_dir() . '/acme_sales_documents';
        $fs->mkdir($tempDir);

        $documentTypes = [
            ['type' => 'invoice', 'prefix' => 'FV'],
            ['type' => 'credit_note', 'prefix' => 'KOR'],
            ['type' => 'receipt', 'prefix' => 'PAR']
        ];

        $generatedCount = 0;
        $documentNumber = 1;

        foreach ($customerUsers as $customerUser) {
            foreach ($documentTypes as $docType) {
                for ($i = 0; $i < 2; $i++) {
                    $docNumber = sprintf('%s/%04d/2025', $docType['prefix'], $documentNumber++);
                    $fileName = str_replace('/', '_', $docNumber) . '.pdf';
                    
                    // Create a simple PDF content
                    $content = $this->generateDocumentContent($customerUser, $docNumber, $docType['type']);
                    $tempFile = $tempDir . '/' . $fileName;
                    
                    // Create a simple PDF
                    $pdfContent = $this->createSimplePDF($content);
                    file_put_contents($tempFile, $pdfContent);

                    // Create attachment file entity
                    $file = new \Symfony\Component\HttpFoundation\File\File($tempFile);
                    $attachmentFile = $this->fileManager->createFileEntity($file);
                    $attachmentFile->setOriginalFilename($fileName);
                    
                    // Create sales document
                    $salesDocument = new SalesDocument();
                    $salesDocument->setDocumentNumber($docNumber);
                    $salesDocument->setDocumentType($docType['type']);
                    
                    // Set document date (1-30 days ago)
                    $documentDate = new \DateTime(sprintf('-%d days', rand(1, 30)));
                    $salesDocument->setDocumentDate($documentDate);
                    
                    // Set amount
                    $amount = rand(100, 5000) + rand(0, 99) / 100;
                    $salesDocument->setAmount($amount);
                    $salesDocument->setCurrency('PLN');
                    
                    // Set due date (document date + 14-30 days)
                    $dueDate = clone $documentDate;
                    $dueDate->add(new \DateInterval(sprintf('P%dD', rand(14, 30))));
                    $salesDocument->setDueDate($dueDate);
                    
                    // Set amount paid (0 to full amount, some unpaid)
                    if (rand(0, 100) < 70) { // 70% chance of payment
                        $amountPaid = rand(0, 100) < 80 ? $amount : round($amount * (rand(10, 90) / 100), 2);
                    } else {
                        $amountPaid = 0; // 30% unpaid
                    }
                    $salesDocument->setAmountPaid($amountPaid);
                    
                    $salesDocument->setCustomerUser($customerUser);
                    $salesDocument->setFile($attachmentFile);
                    $salesDocument->setOrganization($organization);
                    $salesDocument->setErpId('ERP-' . uniqid());
                    
                    $this->em->persist($salesDocument);
                    
                    $io->success(sprintf(
                        'Generated %s %s for %s', 
                        $docType['type'],
                        $docNumber,
                        $customerUser->getEmail()
                    ));
                    $generatedCount++;
                }
            }
        }

        $this->em->flush();

        // Clean up temp files
        $fs->remove($tempDir);

        $io->success(sprintf('Generated %d sales documents successfully!', $generatedCount));

        return Command::SUCCESS;
    }

    private function generateDocumentContent(CustomerUser $user, string $docNumber, string $type): string
    {
        $typeLabels = [
            'invoice' => 'FAKTURA VAT',
            'credit_note' => 'KOREKTA FAKTURY',
            'receipt' => 'PARAGON FISKALNY'
        ];

        $content = $typeLabels[$type] . "\n";
        $content .= str_repeat('=', 50) . "\n\n";
        
        $content .= "Numer: " . $docNumber . "\n";
        $content .= "Data: " . date('Y-m-d') . "\n\n";
        
        $content .= "Nabywca:\n";
        $content .= $user->getFullName() . "\n";
        $content .= $user->getEmail() . "\n\n";
        
        $content .= str_repeat('-', 50) . "\n";
        $content .= "Szczegóły:\n";
        $content .= str_repeat('-', 50) . "\n\n";
        
        // Add some sample items
        $items = rand(1, 5);
        $total = 0;
        for ($i = 1; $i <= $items; $i++) {
            $price = rand(10, 500);
            $qty = rand(1, 10);
            $subtotal = $price * $qty;
            $total += $subtotal;
            
            $content .= sprintf(
                "%d. Produkt %d - %d szt. x %s PLN = %s PLN\n",
                $i,
                rand(1000, 9999),
                $qty,
                number_format($price, 2),
                number_format($subtotal, 2)
            );
        }
        
        $content .= "\n" . str_repeat('-', 50) . "\n";
        $content .= sprintf("RAZEM: %s PLN\n", number_format($total, 2));
        $content .= str_repeat('=', 50) . "\n";
        
        return $content;
    }

    private function createSimplePDF(string $content): string
    {
        // Create a very basic PDF structure
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n";
        
        // Convert content to PDF commands
        $lines = explode("\n", $content);
        $pdfCommands = "BT\n/F1 10 Tf\n50 700 Td\n";
        $y = 700;
        
        foreach ($lines as $line) {
            if ($y < 50) break; // Stop if we reach bottom of page
            $pdfCommands .= "(" . str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $line) . ") Tj\n";
            $y -= 12;
            $pdfCommands .= "0 -12 Td\n";
        }
        $pdfCommands .= "ET\n";
        
        $pdf .= "5 0 obj\n<< /Length " . strlen($pdfCommands) . " >>\nstream\n" . $pdfCommands . "endstream\nendobj\n";
        
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f\n";
        $pdf .= "0000000009 00000 n\n";
        $pdf .= "0000000058 00000 n\n";
        $pdf .= "0000000115 00000 n\n";
        $pdf .= "0000000260 00000 n\n";
        $pdf .= "0000000333 00000 n\n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . (strlen($pdf) + 20) . "\n%%EOF";
        
        return $pdf;
    }
}