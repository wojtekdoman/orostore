#!/usr/bin/env php
<?php
/**
 * Script to import product images from demo data to attachment storage
 */

// Get source and destination paths
$sourceDir = __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/ProductBundle/Migrations/Data/Demo/ORM/images';
$destDir = __DIR__ . '/../var/data/attachments';

// Create destination directory if it doesn't exist
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
    echo "Created directory: $destDir\n";
}

// Database connection details
$dbHost = 'pgsql';
$dbPort = '5432';
$dbName = 'oro_db';
$dbUser = 'oro_db_user';
$dbPass = 'oro_db_pass';

try {
    // Connect to database
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all product images from database
    $sql = "SELECT af.filename, af.original_filename 
            FROM oro_attachment_file af 
            JOIN oro_product_image pi ON pi.image_id = af.id";
    
    $stmt = $pdo->query($sql);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $copiedCount = 0;
    $notFoundCount = 0;
    
    foreach ($images as $image) {
        $dbFilename = $image['filename'];
        $originalName = $image['original_filename'];
        
        // Try to find the source file
        $sourceFile = $sourceDir . '/' . $originalName;
        
        if (file_exists($sourceFile)) {
            // Create subdirectory structure (first 2 chars of hash)
            $subDir = substr($dbFilename, 0, 2);
            $targetDir = $destDir . '/' . $subDir;
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $targetFile = $targetDir . '/' . $dbFilename;
            
            // Copy the file
            if (copy($sourceFile, $targetFile)) {
                echo "✓ Copied: $originalName -> $dbFilename\n";
                $copiedCount++;
            } else {
                echo "✗ Failed to copy: $originalName\n";
            }
        } else {
            echo "✗ Not found: $originalName\n";
            $notFoundCount++;
        }
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "- Total images in database: " . count($images) . "\n";
    echo "- Successfully copied: $copiedCount\n";
    echo "- Not found: $notFoundCount\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}