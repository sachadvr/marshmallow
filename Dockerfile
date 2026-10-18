# syntax=docker/dockerfile:1
FROM node:24-trixie-slim AS web_build
COPY --from=oven/bun:1.3 /usr/local/bin/bun /usr/local/bin/bun
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY vite.config.ts tsconfig.json tsconfig.app.json tsconfig.node.json ./
COPY assets/ ./assets/
RUN bun run build

FROM dunglas/frankenphp:1.13.1-php8.5-trixie AS base
WORKDIR /app
RUN install-php-extensions @composer zip
ENV COMPOSER_ALLOW_SUPERUSER=1 PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
COPY docker/php/app.ini $PHP_INI_DIR/app.conf.d/10-app.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]
HEALTHCHECK --start-period=30s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics") ? 1 : 0);'
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

FROM base AS dev
ENV APP_ENV=dev FRANKENPHP_WORKER_EXTRA=watch
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0 \
    SERVER_NAME=":80" \
    DATABASE_URL="sqlite:////data/marshmallow.db" \
    FILES_DIR="/data/files"
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/app.prod.ini $PHP_INI_DIR/app.conf.d/20-app.prod.ini
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-autoloader --no-scripts --no-progress
COPY --exclude=assets --exclude=package.json --exclude=bun.lock --exclude=*.ts --exclude=tsconfig*.json \
     --exclude=.prettier* --exclude=docker --exclude=Dockerfile --exclude=compose*.yaml --exclude=Makefile --exclude=.dockerignore . ./
COPY --from=web_build /app/public/build/ ./public/build/
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && composer dump-env prod \
 && composer run-script --no-dev post-install-cmd \
 && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
 && mkdir -p /data/files /data/tmp /config/caddy var \
 && chown -R www-data:www-data /data /config var
USER www-data
VOLUME /data
