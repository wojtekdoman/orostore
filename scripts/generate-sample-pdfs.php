<?php
/**
 * Simple PDF generator for sales documents
 * Creates placeholder PDFs with basic document information
 */

// Create attachments directory if it doesn't exist
$attachmentsDir = __DIR__ . '/../var/data/attachments';
if (!is_dir($attachmentsDir)) {
    mkdir($attachmentsDir, 0777, true);
}

// Simple PDF header
$pdfHeader = "%PDF-1.4\n";
$pdfContent = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$pdfContent .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
$pdfContent .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
$pdfContent .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

// Database connection
$dbParams = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => 'oro_db',
    'user' => 'oro_db_user',
    'password' => 'oro_db_pass'
];

try {
    $pdo = new PDO(
        "pgsql:host={$dbParams['host']};port={$dbParams['port']};dbname={$dbParams['dbname']}",
        $dbParams['user'],
        $dbParams['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all attachment files that need PDFs
    $files = $pdo->query("
        SELECT 
            af.id,
            af.filename,
            af.original_filename,
            sd.document_number,
            sd.document_type,
            sd.amount,
            sd.currency,
            sd.document_date,
            cu.email as customer_email
        FROM oro_attachment_file af
        JOIN acme_sales_document sd ON sd.file_id = af.id
        JOIN oro_customer_user cu ON sd.customer_user_id = cu.id
        ORDER BY af.id
    ");

    $count = 0;
    foreach ($files as $file) {
        // Create directory structure (first 2 chars of filename)
        $subDir = substr($file['filename'], 0, 2);
        $fileDir = $attachmentsDir . '/' . $subDir;
        if (!is_dir($fileDir)) {
            mkdir($fileDir, 0777, true);
        }

        $filePath = $fileDir . '/' . $file['filename'];

        // Generate simple PDF content
        $docInfo = sprintf(
            "Document: %s\nType: %s\nDate: %s\nCustomer: %s\nAmount: %s %s",
            $file['document_number'],
            ucfirst($file['document_type']),
            $file['document_date'],
            $file['customer_email'],
            number_format($file['amount'], 2),
            $file['currency']
        );

        // Create text stream
        $textStream = "5 0 obj\n<< /Length " . (strlen($docInfo) + 50) . " >>\nstream\n";
        $textStream .= "BT\n/F1 12 Tf\n100 700 Td\n(" . str_replace("\n", ") Tj\n100 -20 Td\n(", $docInfo) . ") Tj\nET\n";
        $textStream .= "endstream\nendobj\n";

        // Create xref table
        $xref = "xref\n0 6\n";
        $xref .= "0000000000 65535 f\n";
        $xref .= "0000000009 00000 n\n";
        $xref .= "0000000058 00000 n\n";
        $xref .= "0000000115 00000 n\n";
        $xref .= "0000000229 00000 n\n";
        $xref .= "0000000317 00000 n\n";

        // Create trailer
        $trailer = "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $trailer .= "startxref\n" . (strlen($pdfHeader) + strlen($pdfContent) + strlen($textStream)) . "\n%%EOF";

        // Write PDF file
        $pdfData = $pdfHeader . $pdfContent . $textStream . $xref . $trailer;
        file_put_contents($filePath, $pdfData);
        
        echo "Created PDF: {$filePath} for {$file['document_number']}\n";
        $count++;
    }

    echo "\nTotal PDFs created: {$count}\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}