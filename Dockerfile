# Use PHP 8.1 with Apache and GD preinstalled
FROM php:8.1-apache-bullseye

# Set working directory
WORKDIR /var/www/html

# Install system dependencies (only essentials)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libonig-dev \
    libxml2-dev \
    zip \
    libzip-dev \
    libssl-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring bcmath intl zip xml \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Laravel application
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions for Laravel storage and bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy entrypoint script
COPY entrypoint.sh /var/www/html/entrypoint.sh
RUN chmod +x /var/www/html/entrypoint.sh

# Expose port 10000 (Render default)
EXPOSE 10000

# Run entrypoint to handle migrations + seeders and start server
CMD ["/var/www/html/entrypoint.sh"]
