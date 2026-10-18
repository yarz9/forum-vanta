#!/bin/bash
set -e

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

# Recompile templates if the compiled template cache is empty (fresh install or wiped cache)
echo "=== Checking compiled template cache ==="
if [ -z "$(ls -A /var/www/html/internal_data/code_cache/templates 2>/dev/null)" ]; then
    echo "=== Compiled template cache is empty, recompiling ==="
    php /var/www/html/cmd.php xf-dev:recompile-templates || echo "=== Template recompile failed ==="
else
    echo "=== Compiled template cache present ==="
fi

# Start Apache
echo "=== Starting Apache ==="
exec apache2-foreground
