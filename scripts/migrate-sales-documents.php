<?php
/**
 * Script to migrate sales documents from old database to new database
 * Including user assignments and file attachments
 */

// Database connections
$oldDb = [
    'host' => 'localhost',
    'port' => '5433', // Old database port (from orostore project)
    'dbname' => 'oro_db',
    'user' => 'oro_db_user',
    'password' => 'oro_db_pass'
];

$newDb = [
    'host' => 'localhost', 
    'port' => '5432', // New database port (current project)
    'dbname' => 'oro_db',
    'user' => 'oro_db_user',
    'password' => 'oro_db_pass'
];

try {
    // Connect to old database
    $oldConn = new PDO(
        "pgsql:host={$oldDb['host']};port={$oldDb['port']};dbname={$oldDb['dbname']}",
        $oldDb['user'],
        $oldDb['password']
    );
    $oldConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to old database\n";

    // Connect to new database
    $newConn = new PDO(
        "pgsql:host={$newDb['host']};port={$newDb['port']};dbname={$newDb['dbname']}",
        $newDb['user'],
        $newDb['password']
    );
    $newConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to new database\n";

    // 1. Check if sales document table exists in old database
    $checkTable = $oldConn->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'acme_sales_document'
        )
    ");
    $tableExists = $checkTable->fetchColumn();
    
    if (!$tableExists) {
        die("Error: acme_sales_document table does not exist in old database\n");
    }

    // 2. Get user mapping between databases
    echo "\nMapping users between databases...\n";
    $userMapping = [];
    
    // Get users from old database
    $oldUsers = $oldConn->query("
        SELECT id, username, email 
        FROM oro_user 
        ORDER BY id
    ");
    
    foreach ($oldUsers as $oldUser) {
        // Find corresponding user in new database
        $newUserStmt = $newConn->prepare("
            SELECT id FROM oro_user 
            WHERE username = :username OR email = :email
            LIMIT 1
        ");
        $newUserStmt->execute([
            'username' => $oldUser['username'],
            'email' => $oldUser['email']
        ]);
        $newUserId = $newUserStmt->fetchColumn();
        
        if ($newUserId) {
            $userMapping[$oldUser['id']] = $newUserId;
            echo "Mapped user {$oldUser['username']} ({$oldUser['id']} -> {$newUserId})\n";
        }
    }

    // 3. Get customer user mapping
    echo "\nMapping customer users between databases...\n";
    $customerUserMapping = [];
    
    $oldCustomerUsers = $oldConn->query("
        SELECT cu.id, cu.username, cu.email, c.name as customer_name
        FROM oro_customer_user cu
        LEFT JOIN oro_customer c ON cu.customer_id = c.id
        ORDER BY cu.id
    ");
    
    foreach ($oldCustomerUsers as $oldCU) {
        // Find corresponding customer user in new database
        $newCUStmt = $newConn->prepare("
            SELECT cu.id 
            FROM oro_customer_user cu
            WHERE cu.username = :username OR cu.email = :email
            LIMIT 1
        ");
        $newCUStmt->execute([
            'username' => $oldCU['username'],
            'email' => $oldCU['email']
        ]);
        $newCUId = $newCUStmt->fetchColumn();
        
        if ($newCUId) {
            $customerUserMapping[$oldCU['id']] = $newCUId;
            echo "Mapped customer user {$oldCU['username']} ({$oldCU['id']} -> {$newCUId})\n";
        }
    }

    // 4. Get organization mapping (usually just ID 1)
    $orgMapping = [];
    $oldOrgs = $oldConn->query("SELECT id, name FROM oro_organization");
    foreach ($oldOrgs as $org) {
        $newOrgStmt = $newConn->prepare("SELECT id FROM oro_organization WHERE name = :name");
        $newOrgStmt->execute(['name' => $org['name']]);
        $newOrgId = $newOrgStmt->fetchColumn();
        if ($newOrgId) {
            $orgMapping[$org['id']] = $newOrgId;
        }
    }

    // Default to organization 1 if no mapping found
    if (empty($orgMapping)) {
        $orgMapping[1] = 1;
    }

    // 5. Count documents to migrate
    $countStmt = $oldConn->query("SELECT COUNT(*) FROM acme_sales_document");
    $totalDocs = $countStmt->fetchColumn();
    echo "\nFound {$totalDocs} sales documents to migrate\n";

    if ($totalDocs == 0) {
        echo "No sales documents found in old database\n";
        exit(0);
    }

    // 6. Get sales documents from old database
    $documents = $oldConn->query("
        SELECT 
            sd.*,
            af.filename,
            af.original_filename,
            af.file_size,
            af.mime_type,
            af.extension
        FROM acme_sales_document sd
        LEFT JOIN oro_attachment_file af ON sd.file_id = af.id
        ORDER BY sd.id
    ");

    $migratedCount = 0;
    $skippedCount = 0;
    $filesCopied = 0;

    // Start transaction
    $newConn->beginTransaction();

    foreach ($documents as $doc) {
        try {
            // Check if document already exists
            $existsStmt = $newConn->prepare("
                SELECT id FROM acme_sales_document 
                WHERE document_number = :doc_number 
                AND organization_id = :org_id
            ");
            $existsStmt->execute([
                'doc_number' => $doc['document_number'],
                'org_id' => $orgMapping[$doc['organization_id']] ?? 1
            ]);
            
            if ($existsStmt->fetchColumn()) {
                echo "Document {$doc['document_number']} already exists, skipping...\n";
                $skippedCount++;
                continue;
            }

            // Map user IDs
            $newCustomerUserId = null;
            if ($doc['customer_user_id'] && isset($customerUserMapping[$doc['customer_user_id']])) {
                $newCustomerUserId = $customerUserMapping[$doc['customer_user_id']];
            }

            $newUserId = null;
            if ($doc['user_owner_id'] && isset($userMapping[$doc['user_owner_id']])) {
                $newUserId = $userMapping[$doc['user_owner_id']];
            }

            // Handle file attachment
            $newFileId = null;
            if ($doc['file_id'] && $doc['filename']) {
                // Create new file record
                $fileStmt = $newConn->prepare("
                    INSERT INTO oro_attachment_file 
                    (filename, original_filename, file_size, mime_type, extension, created_at, updated_at, owner_user_id)
                    VALUES (:filename, :original_filename, :file_size, :mime_type, :extension, :created_at, :updated_at, :owner_user_id)
                    RETURNING id
                ");
                $fileStmt->execute([
                    'filename' => $doc['filename'],
                    'original_filename' => $doc['original_filename'],
                    'file_size' => $doc['file_size'],
                    'mime_type' => $doc['mime_type'],
                    'extension' => $doc['extension'],
                    'created_at' => $doc['created_at'],
                    'updated_at' => $doc['updated_at'],
                    'owner_user_id' => $newUserId
                ]);
                $newFileId = $fileStmt->fetchColumn();
                
                // Note: Actual file copying would need to be done separately
                echo "Created file record for {$doc['original_filename']} (ID: {$newFileId})\n";
                $filesCopied++;
            }

            // Insert sales document
            $insertStmt = $newConn->prepare("
                INSERT INTO acme_sales_document 
                (customer_user_id, file_id, organization_id, user_owner_id, document_number, 
                 document_type, document_date, amount, currency, erp_id, created_at, 
                 updated_at, due_date, amount_paid)
                VALUES 
                (:customer_user_id, :file_id, :organization_id, :user_owner_id, :document_number,
                 :document_type, :document_date, :amount, :currency, :erp_id, :created_at,
                 :updated_at, :due_date, :amount_paid)
            ");

            $insertStmt->execute([
                'customer_user_id' => $newCustomerUserId,
                'file_id' => $newFileId ?: $doc['file_id'], // Use new file ID or keep original
                'organization_id' => $orgMapping[$doc['organization_id']] ?? 1,
                'user_owner_id' => $newUserId,
                'document_number' => $doc['document_number'],
                'document_type' => $doc['document_type'],
                'document_date' => $doc['document_date'],
                'amount' => $doc['amount'],
                'currency' => $doc['currency'],
                'erp_id' => $doc['erp_id'],
                'created_at' => $doc['created_at'],
                'updated_at' => $doc['updated_at'],
                'due_date' => $doc['due_date'],
                'amount_paid' => $doc['amount_paid']
            ]);

            echo "Migrated document: {$doc['document_number']}\n";
            $migratedCount++;

        } catch (Exception $e) {
            echo "Error migrating document {$doc['document_number']}: " . $e->getMessage() . "\n";
            // Continue with next document
        }
    }

    // Commit transaction
    $newConn->commit();

    echo "\n=== Migration Summary ===\n";
    echo "Total documents: {$totalDocs}\n";
    echo "Migrated: {$migratedCount}\n";
    echo "Skipped (already exist): {$skippedCount}\n";
    echo "File records created: {$filesCopied}\n";
    echo "\nMigration completed successfully!\n";

    // Note about file copying
    if ($filesCopied > 0) {
        echo "\nNOTE: File records were created but actual files need to be copied manually.\n";
        echo "Copy files from: /home/wojtek/projects/orostore/var/data/attachments/\n";
        echo "To: /home/wojtek/projects/orostore-fresh/var/data/attachments/\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    if (isset($newConn)) {
        $newConn->rollBack();
    }
    exit(1);
}