FROM php:8.3-apache

# Install system dependencies
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

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# CORREÇÃO: Configurar Apache para servir a pasta /public
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# CORREÇÃO: Configurar permissões do diretório (versão simplificada)
RUN echo "<Directory /var/www/html/public>" >> /etc/apache2/apache2.conf
RUN echo "    Options Indexes FollowSymLinks" >> /etc/apache2/apache2.conf
RUN echo "    AllowOverride All" >> /etc/apache2/apache2.conf
RUN echo "    Require all granted" >> /etc/apache2/apache2.conf
RUN echo "</Directory>" >> /etc/apache2/apache2.conf

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate key
RUN php artisan key:generate

# Cache configs
RUN php artisan config:cache

EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
