FROM php:8.2-fpm

# Устанавливаем системные зависимости через менеджер пакетов Debian (apt)
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Компилируем и устанавливаем расширения:
# intl (для CI4), pdo_pgsql/pgsql (для Postgres), sockets/bcmath (для RabbitMQ)
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_pgsql \
        pgsql \
        sockets \
        bcmath

WORKDIR /usr/local/apache2/htdocs
