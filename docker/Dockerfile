FROM php:8.1-fpm

LABEL maintainer="Jon Erickson"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd xdebug exif mysqli pdo_mysql zip redis ioncube_loader bcmath gmp intl soap

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY php.ini "$PHP_INI_DIR/conf.d/99-whmcs.ini"