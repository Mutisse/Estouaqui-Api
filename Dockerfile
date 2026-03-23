FROM php:8.2-apache

# Install system dependencies (apenas os essenciais)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (usando versões pré-compiladas)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Configure Apache
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf && \
    sed -i '/<Directory \/var\/www\/html>/a \ \ \ \ Options Indexes FollowSymLinks\n \ \ \ \ AllowOverride All\n \ \ \ \ Require all granted' /etc/apache2/apache2.conf

# Install PHP dependencies (apenas produção, sem dev)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts && \
    composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate key
RUN php artisan key:generate

EXPOSE 80
