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

#### 3. Clear Caches and Reindex

```bash
# Clear Symfony cache
docker compose exec php-fpm php bin/console cache:clear

# Clear image cache
docker compose exec php-fpm php bin/console liip:imagine:cache:remove

# Rebuild assets (if needed)
docker compose exec php-fpm php bin/console cache:warmup
npm run build

# IMPORTANT: Reindex website search to update WebP URLs
docker compose exec php-fpm php bin/console oro:website-search:reindex
```

### Verification

After completing these steps, check that:
1. Product images display correctly in widgets and listings
2. HTML shows proper WebP sources: `<source srcset="/media/cache/attachment/filter/product_large/.../image.jpg.webp" type="image/webp">`
3. No more references to `no_image.png.webp` in product listings

### Additional Notes

- The reindex step is crucial as OroCommerce caches image URLs in the search index
- If issues persist, check browser console for 404 errors on image URLs
- Ensure nginx/Apache has proper permissions to write to `public/media/cache/` directory
- For WYSIWYG images, you may need additional filter configurations

### Related Issues

- WYSIWYG images returning 404: Add `wysiwyg_original` filter to configuration
- Category images not loading: Similar solution but may require different filter names
- Images work in backend but not frontend: Usually indicates a caching or indexing issue