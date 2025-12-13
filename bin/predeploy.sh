#!/bin/bash
set -e

# Defaults
RUN_MIGRATIONS=false
RUN_ASSETS=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --migrations) RUN_MIGRATIONS=true ;;
        --assets) RUN_ASSETS=true ;;
        --prod) RUN_MIGRATIONS=true; RUN_ASSETS=true ;;
    esac
done

# Always run these
php bin/console messenger:stop-workers --env=prod 2>/dev/null || true
php bin/console importmap:install
# not used right now in trans
# php bin/console fos:js-routing:dump --format=js --target=public/js/fos_js_routes.js --callback="export default "

# Assets (production only)
if [ "$RUN_ASSETS" = true ]; then
    php bin/console secrets:decrypt-to-local --force --env=prod 2>/dev/null || true
    php bin/console asset-map:compile
fi

# Migrations (production only)
if [ "$RUN_MIGRATIONS" = true ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

echo "predeploy complete"
