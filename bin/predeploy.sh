#!/bin/bash
set -e

# Defaults
RUN_MIGRATIONS=false
RUN_ASSETS=false
RUN_LOCAL=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --migrations) RUN_MIGRATIONS=true ;;
        --assets) RUN_ASSETS=true ;;
        --prod) RUN_MIGRATIONS=true; RUN_ASSETS=true ;;
        --local) RUN_LOCAL=true ;;
    esac
done

# Local dev setup
if [ "$RUN_LOCAL" = true ]; then
    if ! grep -q "^DATABASE_URL=.*sqlite" .env.local 2>/dev/null; then
        echo "DATABASE_URL=\"sqlite:///%kernel.project_dir%/var/data.db\"" >> .env.local
        echo "Added sqlite DATABASE_URL to .env.local"
    fi
    php bin/console doctrine:schema:update --force
fi

# Always run these
php bin/console messenger:stop-workers --env=prod 2>/dev/null || true
php bin/console importmap:install
# php bin/console fos:js-routing:dump --format=js --target=public/js/fos_js_routes.js --callback="export default "

# Assets/secrets (production only)
if [ "$RUN_ASSETS" = true ]; then
    php bin/console secrets:decrypt-to-local --force --env=prod 2>/dev/null || true
    php bin/console asset-map:compile
fi

# Migrations (production only)
if [ "$RUN_MIGRATIONS" = true ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

echo "predeploy complete"
