#!/usr/bin/env bash
# NEXVARY CX Cloud — Update Script
set -euo pipefail
INSTALL_DIR="/opt/nexvary-cx"
LOG="/var/log/nexvary-cx/update-$(date +%Y%m%d-%H%M%S).log"

echo "NEXVARY CX Cloud Update" | tee "$LOG"
cd "$INSTALL_DIR"
git pull origin main 2>&1 | tee -a "$LOG"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev 2>&1 | tee -a "$LOG"
npm ci 2>&1 | tee -a "$LOG"
npm run build 2>&1 | tee -a "$LOG"
php artisan migrate --force 2>&1 | tee -a "$LOG"
php artisan config:cache 2>&1 | tee -a "$LOG"
php artisan route:cache 2>&1 | tee -a "$LOG"
php artisan view:cache 2>&1 | tee -a "$LOG"
systemctl restart nexvary-queue nexvary-rtc 2>&1 | tee -a "$LOG"
systemctl reload nginx php8.3-fpm 2>&1 | tee -a "$LOG"
echo "Update complete" | tee -a "$LOG"
