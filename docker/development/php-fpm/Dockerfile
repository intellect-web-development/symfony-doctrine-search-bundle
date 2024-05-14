FROM php:8.2-fpm-alpine

RUN apk add --update linux-headers

RUN apk add --no-cache fcgi bash coreutils libmcrypt-dev autoconf g++ make wget \
    && pecl install xdebug \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable xdebug

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

COPY ./development/php-fpm/entrypoint.sh /usr/local/bin/docker-php-entrypoint
RUN chmod +x /usr/local/bin/docker-php-entrypoint

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN wget https://getcomposer.org/installer -O - -q | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app