#!/bin/bash

# Criar link simbólico se não existir
if [ ! -L public/storage ]; then
    php artisan storage:link
fi

# Ajustar permissões
chmod -R 775 storage bootstrap/cache

# Iniciar o servidor
php artisan serve --host=0.0.0.0 --port=8000
