FROM php:8.2-cli-alpine

# 1. Системные зависимости: Java, poppler-utils (pdftotext), bash, icu-libs и curl (для надежного скачивания)
RUN apk add --no-cache openjdk17-jre poppler-utils curl bash icu-libs

# 2. Установка инструментов сборки и компиляция PHP-модулей sockets и intl
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

# 3. АВТОМАТИЗАЦИЯ: Создаем папку и скачиваем валидный jar-ник напрямую из релизов GitHub
# Флаг -L в curl критически важен — он принудительно следует за редиректами GitHub
RUN mkdir -p /opt/tabula_worker/bin && \
    curl -L "https://github.com/tabulapdf/tabula-java/releases/download/v1.0.5/tabula-1.0.5-jar-with-dependencies.jar" -o /opt/tabula_worker/bin/tabula.jar

WORKDIR /usr/local/apache2/htdocs

CMD ["/bin/bash"]
