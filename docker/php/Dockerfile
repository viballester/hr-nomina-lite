FROM php:8.3-cli-alpine
RUN apk add --no-cache icu-dev icu-data-full && docker-php-ext-install intl pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html