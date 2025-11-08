# Base image with PHP 8.1 and Apache
FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions required for Laravel
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libonig-dev \
    libxml2-dev \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libssl-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath xml intl gd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg

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

# Use entrypoint to run migrations + seeders and start server
CMD ["/var/www/html/entrypoint.sh"]
