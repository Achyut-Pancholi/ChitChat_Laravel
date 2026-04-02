#!/usr/bin/env bash
# Exit on error
set -o errexit

echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "Installing npm dependencies and building assets..."
npm install
npm run build

echo "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force
