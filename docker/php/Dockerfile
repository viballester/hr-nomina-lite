FROM php:8.3-cli-alpine
RUN apk add --no-cache icu-dev icu-data-full && docker-php-ext-install intl pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
WORKDIR /var/www/html
ENTRYPOINT ["entrypoint.sh"]
