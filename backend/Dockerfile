FROM php:8.3-fpm

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl libpng-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir diretório
WORKDIR /backend

COPY . .

RUN composer install

CMD ["php-fpm"]
