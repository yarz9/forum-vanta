#!/bin/bash
set -e

# Persist uploaded files (avatars, profile banners, attachments) across deploys.
# Mount a Railway volume at /var/www/html/storage so this survives redeploys.
echo "=== Preparing persistent storage ==="
mkdir -p /var/www/html/storage/data /var/www/html/storage/internal_data

if [ ! -L /var/www/html/data ]; then
    rm -rf /var/www/html/data
    ln -s /var/www/html/storage/data /var/www/html/data
fi

if [ ! -L /var/www/html/internal_data ]; then
    rm -rf /var/www/html/internal_data
    ln -s /var/www/html/storage/internal_data /var/www/html/internal_data
fi

mkdir -p /var/www/html/internal_data/code_cache/templates
chown www-data:www-data /var/www/html/storage /var/www/html/storage/data /var/www/html/storage/internal_data 2>/dev/null || true

echo "=== Configuring Apache ==="

# Remove ALL mpm symlinks from mods-enabled
find /etc/apache2/mods-enabled -name "mpm_*" -delete 2>/dev/null || true

# Enable only mpm_prefork (required for PHP mod_php)
a2enmod mpm_prefork 2>/dev/null || true

# Remove default SSL site config
rm -f /etc/apache2/sites-enabled/default-ssl.conf 2>/dev/null || true

# Set ServerName
APACHE_SERVER_NAME="${SERVER_NAME:-localhost}"
if grep -q "^ServerName" /etc/apache2/apache2.conf; then
    sed -i "s/^ServerName.*/ServerName ${APACHE_SERVER_NAME}/" /etc/apache2/apache2.conf
else
    echo "ServerName ${APACHE_SERVER_NAME}" >> /etc/apache2/apache2.conf
fi

# Handle Railway PORT
APP_PORT="${PORT:-80}"
echo "=== Configuring Apache to listen on PORT ${APP_PORT} ==="

cat > /etc/apache2/ports.conf << EOF
Listen ${APP_PORT}
EOF

cat > /etc/apache2/sites-enabled/000-default.conf << EOF
<VirtualHost *:${APP_PORT}>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

# Test config
apache2ctl configtest 2>&1

# Run XenForo install only if the database has no existing installation
echo "=== Checking if XenForo is installed ==="
if php -r '
require "/var/www/html/src/XF.php";
\XF::start("/var/www/html");
try {
    \XF::app()->db()->fetchOne("SELECT COUNT(*) FROM xf_addon");
    exit(0);
} catch (\Throwable $e) {
    exit(1);
}
' >/dev/null 2>&1; then
    echo "=== XenForo is already installed ==="
else
    echo "=== No existing installation found, running fresh install ==="
    if php /var/www/html/cmd.php xf:install \
        --user=admin \
        --password=VantaForum2026! \
        --email=admin@vanta-forum.com \
        --title="Vanta Forum" \
        --url="${APP_URL:-https://vantacodes.xyz}" \
        --skip-statistics \
        --no-interaction; then
        echo "=== XenForo install completed successfully ==="
    else
        echo "=== XenForo install failed ==="
    fi
fi

# Ensure Vanta add-ons are installed and up to date
echo "=== Ensuring Vanta add-ons ==="
for VA_ADDON in "Vanta/Loader" "Vanta/Tickets" "Vanta/Forum"; do
    VA_INSTALLED=$(php -r '
require "/var/www/html/src/XF.php";
\XF::start("/var/www/html");
try {
    echo (int) \XF::app()->db()->fetchOne("SELECT COUNT(*) FROM xf_addon WHERE addon_id = ?", ["'"$VA_ADDON"'"]);
} catch (\Throwable $e) {
    echo "0";
}
' 2>/dev/null || echo "0")
    if [ "$VA_INSTALLED" = "1" ]; then
        php /var/www/html/cmd.php xf:addon-upgrade "$VA_ADDON" --force --skip-unavailable --no-interaction || echo "=== Could not upgrade $VA_ADDON ==="
    else
        php /var/www/html/cmd.php xf:addon-install "$VA_ADDON" --force --no-interaction || echo "=== Could not install $VA_ADDON ==="
    fi
done

# Always recompile templates so deploy-time template and add-on changes cannot leave a stale/broken template cache
echo "=== Recompiling template cache ==="
php /var/www/html/cmd.php xf-dev:recompile-templates || echo "=== Template recompile failed ==="

# Rebuild core caches (navigation, widgets, options, user groups, banners...).
# internal_data/code_cache is ephemeral on Railway, so without this the
# navigation bar and sidebar widgets render empty after every deploy.
echo "=== Rebuilding core caches ==="
php -r '
require "/var/www/html/src/XF.php";
\XF::start("/var/www/html");
try {
    $job = new \XF\Job\CoreCacheRebuild(\XF::app(), 0, []);
    $job->run(0);
    echo "Core caches rebuilt.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Core cache rebuild failed: " . $e->getMessage() . "\n");
    exit(1);
}
' || echo "=== Core cache rebuild failed ==="

# Hand the persistent storage back to the web user (boot tasks run as root)
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true

# Start Apache
echo "=== Starting Apache ==="
exec apache2-foreground
