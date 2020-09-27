FROM php:7.4-fpm-alpine

# prepare wait-for-it.sh
COPY docker/php/wait-for-it.sh /usr/bin/wait-for-it
RUN chmod +x /usr/bin/wait-for-it

# install php extensions
RUN set -xe \
    && apk add --no-cache --update --virtual .phpize-deps $PHPIZE_DEPS \
    && docker-php-ext-install opcache \
    && docker-php-ext-enable opcache

# redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# cleanup
RUN rm -rf /var/cache/apk/* \
    && rm -rf /usr/share/php \
    && rm -rf /tmp/* \
    && apk del  .phpize-deps

# install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY --chown=www-data . .

# composer & start php-fpm
CMD composer install --no-dev --optimize-autoloader ; \
    echo "WAITING FOR REDIS..." ; \
    wait-for-it redis:6379 ; \
    php-fpm;
