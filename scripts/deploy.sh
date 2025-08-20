#!/bin/bash

# Drupal Deployment Script for Production (LAMP Stack)
# Location on server: /var/www/yt.hardsocket.com/app/scripts/deploy.sh

set -e  # Exit on error

# Configuration
DRUPAL_ROOT="/var/www/yt.hardsocket.com/app"
WEB_ROOT="/var/www/yt.hardsocket.com/app/web"
WEB_USER="www-data"
WEB_GROUP="www-data"
BACKUP_DIR="/var/www/yt.hardsocket.com/app/backups"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Logging functions (output to stdout only, captured by GitHub Actions)
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Start deployment
log "Starting Drupal deployment at $DRUPAL_ROOT..."

# Navigate to Drupal root
cd "$DRUPAL_ROOT" || error "Failed to navigate to Drupal root"

# Verify we're on the correct branch
current_branch=$(git branch --show-current)
log "Current branch: $current_branch"

# Install composer dependencies (production mode)
log "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction 2>&1

# Run database updates
log "Running database updates..."
drush updatedb -y 2>&1 || warning "Database updates may have failed"

# Import configuration (if using config management)
if [ -d "config/sync" ]; then
    log "Importing configuration..."
    drush config:import -y 2>&1 || warning "Configuration import may have failed"
fi

# Clear all caches
log "Clearing Drupal caches..."
drush cache:rebuild 2>&1

# Update file permissions
log "Setting file permissions..."

# Ensure directories exist
mkdir -p "$WEB_ROOT/sites/default/files"
mkdir -p "$WEB_ROOT/sites/default/private"

# Set ownership
chown -R $WEB_USER:$WEB_GROUP "$WEB_ROOT/sites/default/files" 2>&1
chown -R $WEB_USER:$WEB_GROUP "$WEB_ROOT/sites/default/private" 2>&1 || true

# Set directory permissions
find "$WEB_ROOT/sites/default/files" -type d -exec chmod 755 {} \; 2>&1
find "$WEB_ROOT/sites/default/private" -type d -exec chmod 755 {} \; 2>&1 || true

# Set file permissions
find "$WEB_ROOT/sites/default/files" -type f -exec chmod 644 {} \; 2>&1
find "$WEB_ROOT/sites/default/private" -type f -exec chmod 644 {} \; 2>&1 || true

# Protect settings file
chmod 444 "$WEB_ROOT/sites/default/settings.php" 2>&1
chmod 444 "$WEB_ROOT/sites/default/settings.local.php" 2>&1 || true

# Run cron
log "Running cron..."
drush cron 2>&1 || warning "Cron run may have failed"

# Compile theme assets if needed
CUSTOM_THEME_PATH="$WEB_ROOT/themes/custom"
if [ -d "$CUSTOM_THEME_PATH" ]; then
    for theme_dir in "$CUSTOM_THEME_PATH"/*; do
        if [ -f "$theme_dir/package.json" ]; then
            theme_name=$(basename "$theme_dir")
            log "Compiling assets for theme: $theme_name"
            cd "$theme_dir"
            
            # Install npm dependencies if needed
            if [ ! -d "node_modules" ] || [ "package.json" -nt "node_modules" ]; then
                npm ci --production 2>&1
            fi
            
            # Build theme assets
            if [ -f "package.json" ] && grep -q '"build"' package.json; then
                npm run build 2>&1
            fi
            
            cd "$DRUPAL_ROOT"
        fi
    done
fi

# Warm up caches
log "Warming up caches..."
drush cache:rebuild 2>&1

# Check Drupal status
log "Checking Drupal status..."
drush status 2>&1

# Security check
log "Running security check..."
drush pm:security 2>&1 || warning "Security check reported issues"

# Create deployment marker
echo "Deployed on $(date) by GitHub Actions" > "$DRUPAL_ROOT/DEPLOYMENT_INFO.txt"
echo "Commit: $(git rev-parse HEAD)" >> "$DRUPAL_ROOT/DEPLOYMENT_INFO.txt"

# Restart PHP-FPM (if using)
if systemctl is-active --quiet php8.1-fpm; then
    log "Restarting PHP-FPM..."
    sudo systemctl reload php8.1-fpm 2>&1
elif systemctl is-active --quiet php8.2-fpm; then
    log "Restarting PHP-FPM..."
    sudo systemctl reload php8.2-fpm 2>&1
fi

# Restart Apache (graceful reload)
log "Reloading Apache..."
sudo systemctl reload apache2 2>&1

# Final status
log "✅ Drupal deployment completed successfully!"
log "Drupal status:"
drush status --field=drupal-version --field=db-status --field=bootstrap 2>&1

exit 0