#!/usr/bin/env php
<?php
/**
 * Script to import all missing images including WebP files, categories, and landing pages
 */

// Database connection
$dbHost = 'pgsql';
$dbPort = '5432';
$dbName = 'oro_db';
$dbUser = 'oro_db_user';
$dbPass = 'oro_db_pass';

// Define comprehensive search paths
$searchPaths = [
    'landing-page' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/ORM/data/landing-page',
    'home-page' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/ORM/data/home-page',
    'categories' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CatalogBundle/Migrations/Data/Demo/ORM/images',
    'cms-data' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/data',
    'content-template' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/data/content-template/img',
];

$destDir = __DIR__ . '/../var/data/attachments';

try {
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all attachments that don't exist in destination
    $sql = "SELECT af.id, af.filename, af.original_filename, af.parent_entity_class
            FROM oro_attachment_file af 
            ORDER BY af.id";
    
    $stmt = $pdo->query($sql);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $copiedCount = 0;
    $existsCount = 0;
    $notFoundFiles = [];
    
    echo "Processing " . count($attachments) . " attachments...\n\n";
    
    foreach ($attachments as $attachment) {
        $filename = $attachment['filename'];
        $originalName = $attachment['original_filename'];
        $targetFile = $destDir . '/' . $filename;
        
        // Skip if already exists
        if (file_exists($targetFile)) {
            $existsCount++;
            continue;
        }
        
        $found = false;
        
        // Special handling for different file patterns
        foreach ($searchPaths as $pathName => $searchPath) {
            if (!is_dir($searchPath)) continue;
            
            // Direct file match
            $sourceFile = $searchPath . '/' . $originalName;
            if (file_exists($sourceFile)) {
                if (copy($sourceFile, $targetFile)) {
                    echo "✓ [$pathName] Copied: $originalName\n";
                    $copiedCount++;
                    $found = true;
                    break;
                }
            }
            
            // Try without extension variations for categories (e.g., "9_large.jpg" -> "9.jpg")
            if (preg_match('/^(\d+)_large\.(jpg|png)$/', $originalName, $matches)) {
                $baseFile = $searchPath . '/' . $matches[1] . '.' . $matches[2];
                if (file_exists($baseFile)) {
                    if (copy($baseFile, $targetFile)) {
                        echo "✓ [$pathName] Copied (base): $matches[1].$matches[2] -> $originalName\n";
                        $copiedCount++;
                        $found = true;
                        break;
                    }
                }
            }
        }
        
        if (!$found) {
            $notFoundFiles[$originalName] = $attachment['parent_entity_class'];
        }
    }
    
    // Now copy ALL WebP files from landing-page and home-page even if not in database
    echo "\n--- Copying all WebP files from demo data ---\n";
    
    $webpDirs = [
        'landing-page' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/ORM/data/landing-page',
        'home-page' => __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/ORM/data/home-page',
    ];
    
    foreach ($webpDirs as $dirName => $dir) {
        if (!is_dir($dir)) continue;
        
        $files = glob($dir . '/*.webp');
        foreach ($files as $file) {
            $basename = basename($file);
            
            // Find matching database entry
            $stmt = $pdo->prepare("SELECT filename FROM oro_attachment_file WHERE original_filename = ?");
            $stmt->execute([$basename]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $targetFile = $destDir . '/' . $result['filename'];
                if (!file_exists($targetFile)) {
                    if (copy($file, $targetFile)) {
                        echo "✓ [$dirName] WebP: $basename -> {$result['filename']}\n";
                        $copiedCount++;
                    }
                }
            }
        }
    }
    
    // Copy category images
    echo "\n--- Copying category images ---\n";
    $categoryDir = __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CatalogBundle/Migrations/Data/Demo/ORM/images';
    
    if (is_dir($categoryDir)) {
        for ($i = 1; $i <= 9; $i++) {
            $sourceFile = $categoryDir . '/' . $i . '.jpg';
            if (file_exists($sourceFile)) {
                // Find in database
                $stmt = $pdo->prepare("SELECT filename FROM oro_attachment_file WHERE original_filename IN (?, ?)");
                $stmt->execute([$i . '.jpg', $i . '_large.jpg']);
                
                while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $targetFile = $destDir . '/' . $result['filename'];
                    if (!file_exists($targetFile)) {
                        if (copy($sourceFile, $targetFile)) {
                            echo "✓ [category] Image: $i.jpg -> {$result['filename']}\n";
                            $copiedCount++;
                        }
                    }
                }
            }
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total attachments: " . count($attachments) . "\n";
    echo "Already existed: $existsCount\n";
    echo "Newly copied: $copiedCount\n";
    echo "Still missing: " . count($notFoundFiles) . "\n";
    
    if (count($notFoundFiles) > 0 && count($notFoundFiles) <= 20) {
        echo "\nMissing files:\n";
        foreach ($notFoundFiles as $file => $entity) {
            echo "  - $file (" . basename(str_replace('\\', '/', $entity)) . ")\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}