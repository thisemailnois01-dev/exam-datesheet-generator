#!/usr/bin/env bash
# ==============================================================================
# 🚀 Universal Exam Datesheet Generator - Production Deployment Script
# ==============================================================================
# Usage: ./deploy.sh
# Ensures zero-downtime cache clearing, migration execution, and asset bundling.
# ==============================================================================

set -e

echo "🚀 [1/6] Activating Maintenance Mode..."
php artisan down || true

echo "📦 [2/6] Pulling latest code..."
git pull origin main

echo "📚 [3/6] Installing Composer Dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "🗄️ [4/6] Running Database Migrations..."
php artisan migrate --force

echo "⚡ [5/6] Optimizing Configuration & Route Cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "🎨 [6/6] Building Production Frontend Assets..."
npm ci --prefer-offline --no-audit || npm install
npm run build

echo "🔗 Ensuring Storage Symlink..."
php artisan storage:link || true

echo "✨ Deactivating Maintenance Mode..."
php artisan up

echo "✅ Production Deployment Completed Successfully!"
