FROM php:8.3-fpm

# Install dependencies
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
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory
COPY . .

# 🔥 CONFIGURAR TOKEN DO GITHUB (se tiver)
# Você pode passar como build-arg ou usar variável de ambiente
ARG GITHUB_TOKEN
ENV GITHUB_TOKEN=${GITHUB_TOKEN}

# 🔥 LIMPAR CACHE E INSTALAR COM CONFIGURAÇÕES ADICIONAIS
RUN composer clear-cache && \
    # Tentar instalar com prefer-dist primeiro
    composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist || \
    # Se falhar, tentar com source
    composer install --no-interaction --optimize-autoloader --no-dev --prefer-source || \
    # Última tentativa com ignore-platform-reqs
    composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist --ignore-platform-reqs

# 🔥 LINHA ADICIONADA - FORÇAR CRIAÇÃO DO LINK
RUN php artisan storage:link || true

ENV APP_URL=https://estouaqui-api.onrender.com

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
