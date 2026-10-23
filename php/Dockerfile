FROM php:8.2-fpm

# 1. Системные зависимости + утилиты для Composer + Python для таблиц + pdftotext
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    python3 \
    python3-pip \
    poppler-utils \
    && rm -rf /var/lib/apt/lists/*

# 2. Устанавливаем библиотеку pdfplumber для автоматического парсинга таблиц
RUN pip3 install --no-cache-dir pdfplumber --break-system-packages

# 3. Компилируем расширения PHP (все твои модули на месте)
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_pgsql \
        pgsql \
        mysqli \
        sockets \
        bcmath \
        zip

# 4. Устанавливаем свежий Composer прямо внутрь образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /usr/local/apache2/htdocs

# 5. Твоя стандартная команда запуска
CMD composer install && php-fpm
