FROM php:8.1-fpm-alpine

RUN apk add --no-cache \
		acl \
        fcgi \
        file \
        gettext \
        git \
        vim \
        jpegoptim \
        optipng \
        pngquant \
        zip \
	;

# Install build dependencies and necessary packages
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        zlib-dev \
        libxml2-dev \
        imagemagick-dev \
        freetype-dev \
        libpng-dev \
        libjpeg-turbo-dev \
    ; \
    apk add --no-cache \
        zip \
        libzip \
        libpng \
        libjpeg-turbo \
        libxml2 \
        icu \
        imagemagick \
        freetype \
        postgresql \
        zlib \
    ;

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j$(nproc) \
        intl \
        mysqli \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        zip \
        xml \
        soap \
        exif \
        gd \
        bcmath \
        sockets \
    ;

# Install and enable PECL extensions
RUN pecl install apcu imagick; \
    docker-php-ext-enable \
        apcu \
        opcache \
        imagick \
    ;

# Cleanup unnecessary packages and dependencies
RUN runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache --virtual .api-phpexts-rundeps $runDeps; \
    \
    apk del .build-deps;

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN ln -s $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
COPY docker/php/local.ini $PHP_INI_DIR/conf.d/custom.ini

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /srv/api

ARG APP_ENV=local

COPY . ./

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]

CMD ["php-fpm"]
