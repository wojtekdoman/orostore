# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**OroCommerce Community Edition** - B2B e-commerce platform
- Version: 6.1.x 
- Based on Symfony framework
- PHP 8.4 + PostgreSQL 15+ + Redis
- Docker development environment

## Project Structure

```
orostore/
├── src/                    # Application source code
│   ├── Acme/Bundle/       # Custom bundles (SalesDocumentBundle)
│   └── AppKernel.php      # Application kernel
├── config/                # Configuration files
├── public/                # Web root
├── var/                   # Cache, logs, sessions
├── docker/                # Docker configuration
├── scripts/               # Utility scripts
├── database/backups/      # Database backups
└── webpack.config.js      # Frontend build config
```

## Key Features

### Custom SalesDocumentBundle
- Sales documents management (invoices, orders)
- Customer dashboard widgets
- Payment status tracking
- Due date management
- Frontend customer portal integration

## Development Commands

### Docker Environment
```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# View logs
docker compose logs -f
```

### Application Commands
```bash
# Clear cache
bin/console cache:clear

# Run database migrations
bin/console oro:migration:load --force

# Update database schema
composer schema-update

# Install/update dependencies
composer install
npm install

# Build frontend assets
npm run build        # Production build
npm run watch       # Development watch mode
```

### Testing
```bash
# PHP unit tests
bin/phpunit

# JavaScript tests
npm run test

# Code quality checks
bin/php-cs-fixer fix
bin/phpcs
npm run lint
```

### Database Management
```bash
# Backup database
scripts/backup-database.sh

# Restore database
scripts/restore-database.sh

# Generate sample data
php scripts/generate-sample-sales-documents.php
```

## Configuration

### Environment Variables
Key configuration in `.env` file:
- `ORO_DB_*` - Database connection
- `ORO_REDIS_*` - Redis cache
- `ORO_MAILER_*` - Email settings
- `ORO_SECRET` - Application secret

### URLs
- Frontend: http://localhost:8080
- Admin: http://localhost:8080/admin
- Default admin: admin/admin

## Important Notes

1. **Database**: Uses PostgreSQL 15+, not MySQL
2. **Cache**: Redis recommended for production
3. **Node.js**: Requires v22.9.0+
4. **PHP**: Requires 8.4
5. **Commands**: Use `bin/console`, not `php bin/console`
6. **Upgrades**: Always use `composer upgrade:full` for version upgrades

## Common Tasks

### Adding New Features
1. Create bundle in `src/Acme/Bundle/`
2. Register in `config/bundles.yml`
3. Add routing in `config/oro/routing.yml`
4. Create migrations if needed
5. Clear cache and run migrations

### Modifying Frontend
1. Make changes in bundle Resources/views/
2. Update layouts if needed
3. Run `npm run build` or `npm run watch`
4. Clear cache

### Working with Sales Documents
- Entity: `src/Acme/Bundle/SalesDocumentBundle/Entity/SalesDocument.php`
- Controller: `src/Acme/Bundle/SalesDocumentBundle/Controller/`
- Datagrids: `config/oro/datagrids.yml`
- Translations: `Resources/translations/`

## Troubleshooting

- Clear cache: `bin/console cache:clear`
- Check logs: `var/logs/`
- Database issues: Check migrations status
- Frontend issues: Rebuild assets with `npm run build`
- Permission issues: Check `var/` directory permissions