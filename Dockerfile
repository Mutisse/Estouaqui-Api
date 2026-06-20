FROM php:8.3-fpm

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Extensões PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar código
COPY . .

# Criar SQLite (necessário para cache)
RUN mkdir -p database && touch database/database.sqlite

# Instalar dependências
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Cache (ignorando erros de banco de dados)
RUN php artisan config:cache || true

# Permissões
RUN chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
