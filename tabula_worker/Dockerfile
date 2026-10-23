FROM php:8.2-cli-alpine

# 1. Устанавливаем системные рантайм-зависимости
RUN apk add --no-cache openjdk17-jre poppler-utils wget bash icu-libs

# 2. Компилируем sockets и intl
RUN apk add --no-cache --virtual .build-deps \
        autoconf \
        g++ \
        gcc \
        make \
        libc-dev \
        linux-headers \
        icu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install sockets intl \
    && apk del .build-deps

# Создаем папку, куда примонтируется jar
RUN mkdir -p /opt/tabula_worker/bin

WORKDIR /usr/local/apache2/htdocs

CMD ["/bin/bash"]
