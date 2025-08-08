# Troubleshooting Image Display Issues in OroCommerce

## Problem: Product Images Not Displaying in Widgets and Listings

### Symptoms
- Product images show as broken/missing in Featured Products and New Arrivals widgets
- Images display correctly on product detail pages and in the backend
- Browser shows WebP source pointing to `no_image.png.webp` placeholder
- HTML structure shows incorrect `<source>` tag with placeholder instead of actual product image

### Root Cause
The issue occurs when:
1. PHP GD library lacks WebP support
2. LiipImagine filters for WebP formats are not properly configured
3. Website search index contains outdated/empty WebP image URLs

### Solution

#### 1. Add WebP Support to PHP GD Library

Update your PHP Docker container to include WebP support:

```dockerfile
# In docker/php/Dockerfile
RUN apk add --no-cache \
    libwebp-dev \  # Add this line
    # ... other dependencies

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \  # Add --with-webp
    && docker-php-ext-install -j$(nproc) gd
```

Rebuild the PHP container:
```bash
docker compose build php-fpm
docker compose up -d
```

Verify WebP support:
```bash
docker compose exec php-fpm php -r "print_r(gd_info());" | grep WebP
# Should output: [WebP Support] => 1
```

#### 2. Configure LiipImagine Filters for WebP

Create or update `config/oro/liip_imagine.yml`:

```yaml
liip_imagine:
    filter_sets:
        # Define product image filters with proper WebP support
        product_large:
            quality: 85
            filters:
                thumbnail: { size: [600, 600], mode: inset }
        
        product_large_webp:
            quality: 80
            format: webp
            filters:
                thumbnail: { size: [600, 600], mode: inset }
            
        product_medium:
            quality: 85
            filters:
                thumbnail: { size: [400, 400], mode: inset }
                
        product_medium_webp:
            quality: 80
            format: webp
            filters:
                thumbnail: { size: [400, 400], mode: inset }
                
        product_small:
            quality: 85
            filters:
                thumbnail: { size: [200, 200], mode: inset }
                
        product_small_webp:
            quality: 80
            format: webp
            filters:
                thumbnail: { size: [200, 200], mode: inset }
```

#### 3. Import Product Images from Demo Data

**CRITICAL STEP**: Product images must be copied from vendor demo data to the attachment storage directory.

##### 3.1 Locate Demo Images
Demo product images are stored in:
```
vendor/oro/commerce/src/Oro/Bundle/ProductBundle/Migrations/Data/Demo/ORM/images/
```

##### 3.2 Create Attachment Directory Structure
OroCommerce stores attachments in `var/data/attachments/`:
```bash
docker compose exec php-fpm mkdir -p var/data/attachments
docker compose exec php-fpm chmod -R 777 var/data/attachments
```

##### 3.3 Import Images Script
Create and run the import script `scripts/import-product-images.php`:
```php
#!/usr/bin/env php
<?php
$sourceDir = __DIR__ . '/../vendor/oro/commerce/src/Oro/Bundle/ProductBundle/Migrations/Data/Demo/ORM/images';
$destDir = __DIR__ . '/../var/data/attachments';

// Database connection to get file mappings
$pdo = new PDO('pgsql:host=pgsql;dbname=oro_db', 'oro_db_user', 'oro_db_pass');

$sql = "SELECT af.filename, af.original_filename 
        FROM oro_attachment_file af 
        JOIN oro_product_image pi ON pi.image_id = af.id";

foreach ($pdo->query($sql) as $image) {
    $sourceFile = $sourceDir . '/' . $image['original_filename'];
    $targetFile = $destDir . '/' . $image['filename'];
    
    if (file_exists($sourceFile)) {
        copy($sourceFile, $targetFile);
        echo "✓ Copied: {$image['original_filename']} -> {$image['filename']}\n";
    }
}
```

Run the import:
```bash
docker compose exec php-fpm php scripts/import-product-images.php
```

**Important File Locations**:
- Source images: `vendor/oro/commerce/.../Demo/ORM/images/` (60 product images)
- Target location: `var/data/attachments/` (flat structure, no subdirectories)
- Database stores: hashed filename (e.g., `6886528a278d7255513284.jpg`)
- Original filenames preserved in database for reference

#### 4. Clear Caches and Reindex

```bash
# Clear Symfony cache
docker compose exec php-fpm php bin/console cache:clear --env=prod

# Clear image cache  
docker compose exec php-fpm php bin/console liip:imagine:cache:remove

# Rebuild assets
docker compose exec php-fpm php bin/console oro:assets:install --symlink --env=prod

# IMPORTANT: Reindex website search to update image URLs
docker compose exec php-fpm php bin/console oro:website-search:reindex --env=prod
docker compose exec php-fpm php bin/console oro:search:reindex --env=prod
```

### Verification

After completing these steps, check that:
1. Product images display correctly in widgets and listings
2. Direct attachment URLs work: `http://localhost:81/attachment/get/[id]/[filename].jpg`
3. Images are physically present in `var/data/attachments/`
4. No more 404 errors in browser console for product images

### Additional Notes

- The reindex step is crucial as OroCommerce caches image URLs in the search index
- If issues persist, check browser console for 404 errors on image URLs
- Ensure nginx/Apache has proper permissions to write to `public/media/cache/` directory
- For WYSIWYG images, you may need additional filter configurations

## Problem: Banner and UI Graphics Not Displaying

### Symptoms
- Homepage sliders/banners show broken images
- Hero images and promotional banners missing
- Category banners not loading
- CMS content images broken

### Root Cause
Banner and UI graphics are stored differently than product images:
1. Stored as CMS attachments or Digital Assets
2. May be in different database tables
3. Need separate import process

### Solution

#### 1. Locate Banner/Slider Images in Database
```bash
# Check for slider/banner attachments
docker compose exec pgsql psql -U oro_db_user -d oro_db -c "
SELECT af.id, af.filename, af.original_filename, af.parent_entity_class 
FROM oro_attachment_file af 
WHERE af.original_filename LIKE '%slider%' 
   OR af.original_filename LIKE '%banner%' 
   OR af.original_filename LIKE '%hero%'
   OR af.parent_entity_class LIKE '%Slider%'
   OR af.parent_entity_class LIKE '%CMSPage%'
LIMIT 10;"
```

#### 2. Import Banner Images from Demo Data
Banner demo images are typically in:
```
vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/images/
vendor/oro/customer-portal/src/Oro/Bundle/FrontendBundle/Migrations/Data/Demo/ORM/images/
```

Create import script for banners `scripts/import-banner-images.php`:
```php
#!/usr/bin/env php
<?php
// Search for all demo images in vendor
$vendorPaths = [
    'vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/Demo/ORM/images',
    'vendor/oro/customer-portal/src/Oro/Bundle/FrontendBundle/Migrations/Data/Demo/ORM/images',
];

$destDir = __DIR__ . '/../var/data/attachments';

// Get all non-product attachments from database
$pdo = new PDO('pgsql:host=pgsql;dbname=oro_db', 'oro_db_user', 'oro_db_pass');
$sql = "SELECT af.filename, af.original_filename 
        FROM oro_attachment_file af 
        WHERE af.id NOT IN (SELECT image_id FROM oro_product_image WHERE image_id IS NOT NULL)";

foreach ($pdo->query($sql) as $attachment) {
    $found = false;
    
    // Search in all vendor paths
    foreach ($vendorPaths as $path) {
        $sourceFile = __DIR__ . '/../' . $path . '/' . $attachment['original_filename'];
        if (file_exists($sourceFile)) {
            $targetFile = $destDir . '/' . $attachment['filename'];
            copy($sourceFile, $targetFile);
            echo "✓ Copied banner: {$attachment['original_filename']}\n";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "✗ Not found: {$attachment['original_filename']}\n";
    }
}
```

#### 3. Manual Banner Upload (if demo images not available)
If banner images are not in demo data, you'll need to:

1. Access OroCommerce Admin Panel
2. Navigate to Marketing > Landing Pages or Content > Pages
3. Edit each page/slider
4. Re-upload banner images manually
5. Or use placeholder images temporarily

#### 4. Fix Digital Asset Manager Images
```bash
# Load digital assets demo data
docker compose exec php-fpm bin/console oro:migration:data:load \
    --fixtures-type=demo \
    --bundles=OroDigitalAssetBundle \
    --env=prod
```

## Complete Image Import Solution - All Missing Files

### Final Import Script for ALL Missing Images

After initial imports, you may still have missing WebP files, category images, and landing page graphics. Use this comprehensive script `scripts/import-missing-images.php`:

```php
#!/usr/bin/env php
<?php
// This script imports ALL missing images including:
// - WebP files for sliders (1_360.webp, 2_768.webp, etc.)
// - Category images (1.jpg through 9.jpg)
// - Featured categories grid images
// - Illustration carts (contact-us, order-history)
// - Map files for responsive layouts

$searchPaths = [
    'landing-page' => 'vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/ORM/data/landing-page',
    'home-page' => 'vendor/oro/commerce/src/Oro/Bundle/CMSBundle/Migrations/Data/ORM/data/home-page',
    'categories' => 'vendor/oro/commerce/src/Oro/Bundle/CatalogBundle/Migrations/Data/Demo/ORM/images',
];

// Script automatically finds and copies:
// - 56 slider WebP files (all responsive variants)
// - 28 map WebP files for layouts
// - 30 featured category grid images
// - 9 category JPG images
```

Run the complete import:
```bash
docker compose exec php-fpm php scripts/import-missing-images.php
```

### Image Locations in Demo Data

| Image Type | Source Location | File Examples |
|------------|----------------|---------------|
| **Product Images** | `vendor/oro/commerce/.../ProductBundle/Migrations/Data/Demo/ORM/images/` | `0RT28-*.jpg`, `1AB92-*.jpg` |
| **Slider WebP** | `vendor/oro/commerce/.../CMSBundle/Migrations/Data/ORM/data/landing-page/` | `1_360.webp`, `2_1920-2x.webp` |
| **Home Page Graphics** | `vendor/oro/commerce/.../CMSBundle/Migrations/Data/ORM/data/home-page/` | `featured-categories-grid-img-*.webp` |
| **Category Images** | `vendor/oro/commerce/.../CatalogBundle/Migrations/Data/Demo/ORM/images/` | `1.jpg` through `9.jpg` |
| **Banner Images** | `vendor/oro/commerce/.../CMSBundle/.../content-template/img/` | `banner_360.jpg`, `banner_1920.jpg` |
| **Promo Sliders** | `vendor/oro/commerce/.../CMSBundle/Migrations/Data/Demo/ORM/data/promo-slider/` | `promo-slider-*.png` |

### Final Status After Complete Import

After running all import scripts, you should have:
- **268 total files** in `var/data/attachments/`
- **114 WebP files** (responsive variants)
- **60 product images** (JPG)
- **85 banner/UI graphics** (JPG, PNG, SVG)
- **9 category images** (JPG)

### Files That Cannot Be Imported

The following files are not part of demo data and need to be generated separately:
- Sales Documents PDFs (`FV_*.pdf`, `KOR_*.pdf`, `PAR_*.pdf`) - These are invoices, corrections, and receipts
- Custom uploaded content through admin panel
- User-generated attachments

### Troubleshooting After Import

If images still don't display after import:
1. Clear all caches:
   ```bash
   docker compose exec php-fpm bin/console cache:clear --env=prod
   docker compose exec php-fpm bin/console liip:imagine:cache:remove
   ```

2. Reindex search:
   ```bash
   docker compose exec php-fpm bin/console oro:website-search:reindex --env=prod
   ```

3. Check file permissions:
   ```bash
   docker compose exec php-fpm chmod -R 777 var/data/attachments
   docker compose exec php-fpm chmod -R 777 public/media/cache
   ```

4. Verify files exist:
   ```bash
   docker compose exec php-fpm find var/data/attachments -type f | wc -l
   # Should show 250+ files
   ```

### Related Issues

- WYSIWYG images returning 404: Add `wysiwyg_original` filter to configuration
- Category images not loading: Similar solution but may require different filter names
- Images work in backend but not frontend: Usually indicates a caching or indexing issue
- Slider images missing: Check oro_cms_content_widget_usages table for widget configurations
- WebP files not found: Ensure all paths in searchPaths array are correct in import script