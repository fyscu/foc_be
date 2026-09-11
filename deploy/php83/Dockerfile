ARG PHP_IMAGE=php:8.3-fpm-alpine@sha256:bf90236449d333cef008b1f01c72a3d4f11a6470a74629665e4c6b6158f03fc8
FROM ${PHP_IMAGE}

ARG PHP_EXTENSIONS
ARG TZ=Asia/Shanghai

RUN set -eux; \
    apk add --no-cache shadow tzdata; \
    cp "/usr/share/zoneinfo/${TZ}" /etc/localtime; \
    echo "${TZ}" > /etc/timezone

ADD --checksum=sha256:35ccbf3d530c01e19cdf66fd2bba813c15f748e2f3c3cc6bb0ae0814019e75eb \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.2.14/install-php-extensions \
    /usr/local/bin/install-php-extensions

RUN set -eux; \
    apk add --no-cache --virtual .php-build-deps ${PHPIZE_DEPS}; \
    chmod 0755 /usr/local/bin/install-php-extensions; \
    install-php-extensions ${PHP_EXTENSIONS}; \
    apk del .php-build-deps; \
    usermod -u 1000 www-data; \
    groupmod -g 1000 www-data

RUN apk add --no-cache \
    freetype \
    icu-data-full \
    icu-libs \
    libavif \
    libjpeg-turbo \
    libpng \
    libstdc++

WORKDIR /www
