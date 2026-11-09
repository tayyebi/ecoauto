# DECISION (infra): a Dockerfile exists because the deploy workflow is
# `git pull` + `docker-compose up -d`, and `php:8.4-cli` is missing extensions
# this application cannot start without.
#
# DECISION (infra): the image carries the runtime, never the application. There
# is no COPY of the source; the checkout is bind mounted at run time. That keeps
# a release a `git pull` with nothing to rebuild or push, and it means this
# Dockerfile changes roughly never.
FROM php:8.4-cli

# unzip: Composer extracts dist archives with the system binary, not ext-zip.
# git: needed for Composer's source fallback.
# libonig-dev / libxml2-dev: build deps for mbstring and dom.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        unzip git libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# DECISION (infra): this list is not guesswork. `composer check-platform-reqs`
# against the lock file reports ext-dom, ext-mbstring, ext-fileinfo, ext-ctype,
# ext-filter, ext-hash, ext-iconv, ext-json, ext-libxml, ext-openssl, ext-pcre,
# ext-session and ext-tokenizer. The official image ships most of those but not
# dom, mbstring or pdo_mysql - and dom arrives transitively through
# tijsverkoyen/css-to-inline-styles, so a "mysql only" install fails at boot.
#
# opcache is not required; it is what stops the built-in server recompiling
# every file on every request.
RUN docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring dom fileinfo opcache \
    && docker-php-ext-enable opcache

# Matching the Composer major the lock file was generated with.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# DECISION (infra): the image ships a php.ini, because php:8.4-cli ships none.
# Without it PHP runs its compiled development defaults - display_errors on
# STDOUT, log_errors off, no output buffering - so any notice from any
# dependency is written into the response body, commits the header block, and
# turns a harmless warning into
#   "Cannot modify header information - headers already sent"
# at Response.php:328, with the real exception buried beneath it.
#
# conf.d is the right home for it: the same file then applies to
# `php artisan` (including the entrypoint's store:boot) and to the built-in
# server, so the console and the web path cannot disagree. The name sorts after
# the official zz-docker.ini and every package ini, so these values win.
COPY docker/zz-store.ini /usr/local/etc/php/conf.d/zz-store.ini

WORKDIR /app

# Documentation only; the real mapping lives in docker-compose.yml.
EXPOSE 9020

# bin/bootstrap is the entrypoint for first install and for every restart.
ENTRYPOINT ["/bin/sh", "/app/bin/bootstrap"]
CMD ["php", "artisan", "store:serve"]
