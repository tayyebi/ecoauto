#!/bin/sh
# Runs once, when MariaDB initialises an empty ./data/mysql.
#
# DECISION (infra): the application provisions one database per storefront
# account at runtime, inside a web request, so the application user needs the
# CREATE privilege. Granting it here is what keeps root credentials out of the
# app container entirely.
#
# To apply this grant on an installation whose database is already initialised
# (the normal case after any restart), use ./bin/db-grant instead - this script
# only ever runs on a genuinely empty data directory.
set -eu

: "${MARIADB_USER:?MARIADB_USER must be set}"

# DECISION (infra): MariaDB 11 ships no `mysql` binary - the client is `mariadb`.
# A hardcoded `mysql` fails with "not found" only when this script finally runs,
# i.e. during a fresh install and never before.
if command -v mariadb >/dev/null 2>&1; then
    CLIENT=mariadb
elif command -v mysql >/dev/null 2>&1; then
    CLIENT=mysql
else
    echo "[store-init] no MariaDB client found in PATH" >&2
    exit 1
fi

# MYSQL_PWD avoids flashing the root password in the process list.
MYSQL_PWD="${MARIADB_ROOT_PASSWORD}" "${CLIENT}" --protocol=socket -uroot <<SQL
-- DECISION (db): two patterns because there are two kinds of database. The
-- landlord database is store_landlord; tenant databases are tenant_<ulid>.
-- Granting only store\_% leaves every storefront unable to create its own
-- schema, which only shows up the first time an account is provisioned.
GRANT ALL PRIVILEGES ON \`store\\_%\`.* TO '${MARIADB_USER}'@'%';
GRANT ALL PRIVILEGES ON \`tenant\\_%\`.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

echo "[store-init] granted CREATE on store_\\_% and tenant\\_% to ${MARIADB_USER}"
