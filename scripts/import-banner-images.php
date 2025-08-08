#!/usr/bin/env php
<?php
/**
 * Script to import banner and CMS images from demo data
 */

// Define search paths for different types of images
$searchPaths = [
    __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/data/content-template/img',
    __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/data/promo-slider',
    __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/data',
    __DIR__ . '/../vendor/oro/platform/src/Oro/Bundle/DigitalAssetBundle/Migrations/Data/Demo/ORM/images',
];

$destDir = __DIR__ . '/../var/data/attachments';

// Database connection
$dbHost = 'pgsql';
$dbPort = '5432';
$dbName = 'oro_db';
$dbUser = 'oro_db_user';
$dbPass = 'oro_db_pass';

try {
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all non-product attachments
    $sql = "SELECT af.id, af.filename, af.original_filename, af.parent_entity_class
            FROM oro_attachment_file af 
            WHERE af.id NOT IN (
                SELECT image_id FROM oro_product_image WHERE image_id IS NOT NULL
            )
            ORDER BY af.id";
    
    $stmt = $pdo->query($sql);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $copiedCount = 0;
    $notFoundCount = 0;
    $skippedCount = 0;
    
    echo "Processing " . count($attachments) . " non-product attachments...\n\n";
    
    foreach ($attachments as $attachment) {
        $filename = $attachment['filename'];
        $originalName = $attachment['original_filename'];
        $entityClass = $attachment['parent_entity_class'];
        
        // Check if file already exists
        $targetFile = $destDir . '/' . $filename;
        if (file_exists($targetFile)) {
            echo "⊙ Already exists: $originalName\n";
            $skippedCount++;
            continue;
        }
        
        // Search for the source file
        $found = false;
        
        // Try exact filename match first
        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) continue;
            
            $sourceFile = $searchPath . '/' . $originalName;
            if (file_exists($sourceFile)) {
                if (copy($sourceFile, $targetFile)) {
                    echo "✓ Copied: $originalName (from " . basename(dirname($searchPath)) . ")\n";
                    $copiedCount++;
                    $found = true;
                    break;
                }
            }
            
            // Also search in subdirectories
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($searchPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $originalName) {
                    $sourceFile = $file->getPathname();
                    if (copy($sourceFile, $targetFile)) {
                        echo "✓ Copied: $originalName (from subdirectory)\n";
                        $copiedCount++;
                        $found = true;
                        break 2;
                    }
                }
            }
        }
        
        if (!$found) {
            // Special case for onsale_1.jpg
            if ($originalName === 'onsale_1.jpg') {
                // Create a placeholder sale badge
                $placeholderCreated = createSalePlaceholder($targetFile);
                if ($placeholderCreated) {
                    echo "✓ Created placeholder: $originalName\n";
                    $copiedCount++;
                    $found = true;
                }
            }
            
            if (!$found) {
                echo "✗ Not found: $originalName (Entity: " . basename(str_replace('\\', '/', $entityClass)) . ")\n";
                $notFoundCount++;
            }
        }
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "- Total attachments: " . count($attachments) . "\n";
    echo "- Successfully copied: $copiedCount\n";
    echo "- Already existed: $skippedCount\n";
    echo "- Not found: $notFoundCount\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Create a simple "SALE" placeholder image
 */
function createSalePlaceholder($targetFile) {
    // Try to create a simple red circle with "SALE" text
    if (function_exists('imagecreatetruecolor')) {
        $width = 100;
        $height = 100;
        
        $image = imagecreatetruecolor($width, $height);
        
        // Enable alpha blending
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        
        // Draw red circle
        $red = imagecolorallocate($image, 220, 53, 69);
        imagefilledellipse($image, 50, 50, 90, 90, $red);
        
        // Add white text
        $white = imagecolorallocate($image, 255, 255, 255);
        $text = 'SALE';
        $font = 5; // Built-in font
        
        // Center the text
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        
        imagestring($image, $font, $x, $y, $text, $white);
        
        // Save as JPEG
        imagejpeg($image, $targetFile, 90);
        imagedestroy($image);
        
        return true;
    }
    
    return false;
}