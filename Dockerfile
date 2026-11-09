# DECISION (infra): a Dockerfile exists again, but it is deliberately tiny.
# The deploy workflow is `git pull` + `docker-compose up -d`, and `php:8.4-cli`
# ships without pdo_mysql and without Composer, so *something* has to add them.
#
# DECISION (infra): the image carries the runtime, never the application. There
# is no COPY of the source; the checkout is bind mounted at run time. That keeps
# a release a `git pull` with nothing to rebuild or push, and it means this
# Dockerfile changes roughly never.
FROM php:8.4-cli

# unzip: Composer extracts dist archives with the system binary, not ext-zip.
# git: needed for `--prefer-source` fallback and for anything dev-installed.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

# pdo_mysql is the only extension the app cannot run without; opcache is what
# keeps a from-scratch PHP server from recompiling every file on every request.
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql opcache \
    && docker-php-ext-enable opcache

# Matching the Composer major the lock file was generated with.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Documentation only; the actual mapping is in docker-compose.yml.
EXPOSE 9020

CMD ["php", "artisan", "store:serve"]
