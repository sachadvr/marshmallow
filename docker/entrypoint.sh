#!/bin/sh
set -e
mkdir -p /data/files /data/tmp
if [ "$1" = 'frankenphp' ]; then
	php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing
fi
exec docker-php-entrypoint "$@"
