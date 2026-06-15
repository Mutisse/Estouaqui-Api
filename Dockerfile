FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory
COPY . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# ========== NOVAS LINHAS ADICIONADAS ==========
# Forçar HTTPS nas URLs geradas pela aplicação
ENV APP_URL=https://estouaqui-api.onrender.com
ENV ASSET_URL=https://estouaqui-api.onrender.com

# Limpar cache de configuração antigo (REMOVI O cache:clear que estava dando erro)
RUN php artisan config:clear
RUN php artisan optimize:clear

# Recriar cache com as novas configurações (sem usar cache:clear)
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache
# ===============================================

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
