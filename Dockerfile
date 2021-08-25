FROM php:8.0-fpm-alpine3.13

# Import extension installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

WORKDIR /var/www

RUN apk update && apk add \
    build-base \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    zlib-dev \
    libzip-dev \
    zlib \
    libpng \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl


RUN apk add icu-dev

RUN apk add --no-cache $PHPIZE_DEPS && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    install-php-extensions pdo_mysql bcmath opcache mbstring exif pcntl


RUN docker-php-ext-configure intl && docker-php-ext-install intl

RUN docker-php-ext-configure gd --enable-gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/
RUN docker-php-ext-install gd

RUN docker-php-ext-install zip

# Install Redis Extension
RUN apk add autoconf && pecl install -o -f redis \
&&  rm -rf /tmp/pear \
&&  docker-php-ext-enable redis && apk del autoconf

# Copy config
COPY ./config/php/local.ini /usr/local/etc/php/conf.d/local.ini


RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

USER www

COPY --chown=www:www . /var/www

RUN ["chmod", "+x", "./start_script.sh"]

EXPOSE 9000

CMD ./start_script.sh
