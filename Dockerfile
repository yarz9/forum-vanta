FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    git \
    zip \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    default-mysql-client

# Build PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli mbstring gd xml zip bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create directories
RUN mkdir -p /var/www/html/data /var/www/html/internal_data /var/www/html/internal_data/code_cache/templates

# Copy project files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/html/data \
    && chmod -R 775 /var/www/html/internal_data

# Enable Apache modules and fix MPM
RUN a2enmod rewrite 2>/dev/null; \
    rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null; \
    a2enmod mpm_prefork 2>/dev/null; \
    true

# Enable PHP settings
RUN echo "output_buffering=4096" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini

# Copy startup script
COPY start-apache.sh /usr/local/bin/start-apache.sh
RUN chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80

CMD ["/usr/local/bin/start-apache.sh"]
