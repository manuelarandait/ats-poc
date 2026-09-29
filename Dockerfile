FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_pgsql amqp intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app

CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public/"]
