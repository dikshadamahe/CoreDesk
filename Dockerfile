# Production Dockerfile for Render Deployment
FROM php:8.2-apache

# Install required system libraries and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module for .htaccess support
RUN a2enmod rewrite

# Configure Apache virtual host and directory permissions
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application codebase
COPY . /var/www/html/

# Create startup script to dynamically bind Apache to Render's $PORT
RUN printf '#!/bin/sh\n\
PORT="${PORT:-80}"\n\
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/*.conf\n\
mkdir -p /var/www/html/database\n\
chown -R www-data:www-data /var/www/html/database\n\
chmod -R 775 /var/www/html/database\n\
if [ -f /var/www/html/database/coredesk.sqlite ]; then\n\
    chmod 664 /var/www/html/database/coredesk.sqlite\n\
fi\n\
exec apache2-foreground\n' > /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose default port
EXPOSE 80

# Run entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
