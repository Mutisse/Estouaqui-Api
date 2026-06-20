FROM php:8.3-fpm

# ============================================================
# INSTALAR DEPENDÊNCIAS
# ============================================================
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

# ============================================================
# INSTALAR EXTENSÕES PHP
# ============================================================
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# ============================================================
# INSTALAR COMPOSER
# ============================================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================================
# DEFINIR DIRETÓRIO DE TRABALHO
# ============================================================
WORKDIR /var/www/html

# ============================================================
# COPIAR ARQUIVOS
# ============================================================
COPY . .

# ============================================================
# INSTALAR DEPENDÊNCIAS
# ============================================================
RUN composer install --no-interaction --optimize-autoloader --no-dev

# ============================================================
# 🔥 CONFIGURAR AMBIENTE
# ============================================================
ENV APP_URL=https://estouaqui-api.onrender.com

# ============================================================
# 🔥 LIMPAR E RECONSTRUIR CACHES
# ============================================================
RUN php artisan config:clear \
    && php artisan cache:clear \
    && php artisan view:clear \
    && php artisan route:clear \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# ============================================================
# 🔥 CRIAR LINK STORAGE
# ============================================================
RUN php artisan storage:link || true

# ============================================================
# PERMISSÕES
# ============================================================
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ============================================================
# EXPORTA PORTA
# ============================================================
EXPOSE 8000

# ============================================================
# 🔥 COMANDO DE INÍCIO - LIMPA CACHE E SOBE O SERVIDOR
# ============================================================
CMD php artisan config:clear \
    && php artisan cache:clear \
    && php artisan view:clear \
    && php artisan route:clear \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan serve --host=0.0.0.0 --port=8000
