FROM php:8.2-fpm

# Устанавливаем системные зависимости + утилиты для Composer
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Компилируем расширения PHP
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_pgsql \
        pgsql \
        mysqli \
        sockets \
        bcmath \
        zip

# Устанавливаем свежий Composer прямо внутрь образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /usr/local/apache2/htdocs

CMD composer install && php-fpm
