<?php
/**
 * Script to generate sample sales documents with various payment statuses
 */

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
    echo "Connected to database\n";

    // Get user IDs
    $users = [
        'AmandaRCole@example.org' => null,
        'BrandaJSanborn@example.org' => null
    ];

    foreach ($users as $email => &$userId) {
        $stmt = $pdo->prepare("SELECT id FROM oro_customer_user WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $userId = $stmt->fetchColumn();
        echo "Found user $email with ID: $userId\n";
    }

    // Get organization ID (usually 1)
    $orgId = 1;

    // Document combinations
    $documentTypes = ['invoice', 'credit_note', 'receipt'];
    $paymentStatuses = ['paid', 'unpaid', 'partial'];
    $dueDateOffsets = [-30, -15, -7, 0, 7, 15, 30]; // days from today

    // Start transaction
    $pdo->beginTransaction();

    $docNumber = 1;
    $currentDate = new DateTime();
    $year = $currentDate->format('Y');

    foreach ($users as $email => $userId) {
        foreach ($documentTypes as $docType) {
            foreach ($paymentStatuses as $paymentStatus) {
                foreach ($dueDateOffsets as $offset) {
                    // Generate document data
                    $docDate = clone $currentDate;
                    $docDate->sub(new DateInterval('P' . rand(1, 60) . 'D')); // Random date in past 60 days
                    
                    $dueDate = clone $docDate;
                    $dueDate->add(new DateInterval('P30D')); // Standard 30 days payment term
                    $dueDate->add(new DateInterval('P' . abs($offset) . 'D'));
                    if ($offset < 0) {
                        $dueDate->sub(new DateInterval('P' . (abs($offset) * 2) . 'D'));
                    }

                    // Generate amounts
                    $amount = rand(100, 10000) + (rand(0, 99) / 100);
                    $amountPaid = 0;
                    
                    if ($paymentStatus === 'paid') {
                        $amountPaid = $amount;
                    } elseif ($paymentStatus === 'partial') {
                        $amountPaid = round($amount * (rand(20, 80) / 100), 2);
                    }

                    // Generate document number
                    $prefix = '';
                    switch ($docType) {
                        case 'invoice':
                            $prefix = 'FV';
                            break;
                        case 'credit_note':
                            $prefix = 'KOR';
                            break;
                        case 'receipt':
                            $prefix = 'PAR';
                            break;
                    }
                    $documentNumber = sprintf('%s/%04d/%d', $prefix, $docNumber++, $year);

                    // Create dummy file record
                    $filename = sprintf('sales_doc_%s.pdf', uniqid());
                    $originalFilename = sprintf('%s_%s.pdf', $documentNumber, $email);
                    
                    $fileStmt = $pdo->prepare("
                        INSERT INTO oro_attachment_file 
                        (filename, original_filename, file_size, mime_type, extension, created_at, updated_at)
                        VALUES (:filename, :original_filename, :file_size, :mime_type, :extension, NOW(), NOW())
                        RETURNING id
                    ");
                    
                    $fileStmt->execute([
                        'filename' => $filename,
                        'original_filename' => str_replace('/', '_', $originalFilename),
                        'file_size' => rand(50000, 200000),
                        'mime_type' => 'application/pdf',
                        'extension' => 'pdf'
                    ]);
                    
                    $fileId = $fileStmt->fetchColumn();

                    // Insert sales document
                    $docStmt = $pdo->prepare("
                        INSERT INTO acme_sales_document 
                        (customer_user_id, file_id, organization_id, document_number, document_type, 
                         document_date, amount, currency, erp_id, created_at, updated_at, due_date, amount_paid)
                        VALUES 
                        (:customer_user_id, :file_id, :organization_id, :document_number, :document_type,
                         :document_date, :amount, :currency, :erp_id, NOW(), NOW(), :due_date, :amount_paid)
                    ");

                    $docStmt->execute([
                        'customer_user_id' => $userId,
                        'file_id' => $fileId,
                        'organization_id' => $orgId,
                        'document_number' => $documentNumber,
                        'document_type' => $docType,
                        'document_date' => $docDate->format('Y-m-d'),
                        'amount' => $amount,
                        'currency' => 'USD',
                        'erp_id' => 'ERP-' . uniqid(),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'amount_paid' => $amountPaid
                    ]);

                    echo "Created {$docType} {$documentNumber} for {$email} - ";
                    echo "Status: {$paymentStatus}, Due: {$dueDate->format('Y-m-d')}\n";
                }
            }
        }
    }

    // Commit transaction
    $pdo->commit();
    
    echo "\n=== Summary ===\n";
    $countStmt = $pdo->query("SELECT COUNT(*) FROM acme_sales_document");
    $total = $countStmt->fetchColumn();
    echo "Total documents created: {$total}\n";

    // Show distribution
    $stats = $pdo->query("
        SELECT 
            cu.email,
            sd.document_type,
            COUNT(*) as count,
            SUM(CASE WHEN sd.amount_paid >= sd.amount THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN sd.amount_paid > 0 AND sd.amount_paid < sd.amount THEN 1 ELSE 0 END) as partial,
            SUM(CASE WHEN sd.amount_paid = 0 THEN 1 ELSE 0 END) as unpaid
        FROM acme_sales_document sd
        JOIN oro_customer_user cu ON sd.customer_user_id = cu.id
        GROUP BY cu.email, sd.document_type
        ORDER BY cu.email, sd.document_type
    ");

    echo "\nDocument distribution:\n";
    foreach ($stats as $row) {
        echo "{$row['email']} - {$row['document_type']}: ";
        echo "Total: {$row['count']}, Paid: {$row['paid']}, Partial: {$row['partial']}, Unpaid: {$row['unpaid']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    exit(1);
}