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

# ============================================================
# 🔥 RODAR MIGRATIONS E SEEDERS
# ============================================================
# Limpar cache antes
RUN php artisan config:clear || true
RUN php artisan cache:clear || true

# Rodar migrations
RUN php artisan migrate --force || true

# Rodar seeders
RUN php artisan db:seed --force || true

# Recriar cache
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true

# ============================================================
# PERMISSÕES
# ============================================================
RUN chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 8000

# ============================================================
# CMD com limpeza de cache
# ============================================================
CMD php artisan config:clear \
    && php artisan cache:clear \
    && php artisan view:clear \
    && php artisan route:clear \
    && php artisan migrate --force \
    && php artisan db:seed --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan serve --host=0.0.0.0 --port=8000
