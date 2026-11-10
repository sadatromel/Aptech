#!/bin/bash
set -e

# Run database auto-migration
php /var/www/html/bin/migrate_db.php || true

# Execute main process
exec "$@"

