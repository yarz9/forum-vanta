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

# Start Apache
echo "=== Starting Apache ==="
exec apache2-foreground
