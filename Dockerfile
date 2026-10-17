# =====================================================================
# ThredIt - Production Dockerfile for Render Deployment
# =====================================================================
FROM php:8.2-apache

# 1. Install necessary extensions for MySQL PDO, TLS, and OpCache
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql opcache \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

# 2. Use production PHP ini settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 3. Set working directory
WORKDIR /var/www/html

# 4. Copy source code
COPY . /var/www/html/

# 5. Configure Apache virtual host and dynamic port entrypoint
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

# 6. Expose default port (Render overrides with dynamic $PORT via entrypoint)
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
