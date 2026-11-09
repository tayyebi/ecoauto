#!/bin/sh
# Runs once, when MariaDB initialises an empty ./data/mysql.
#
# DECISION (infra): the application provisions one database per storefront
# account at runtime, inside a web request, so the application user needs the
# CREATE privilege. Granting it here is what keeps root credentials out of the
# app container entirely.
#
# This is a shell script rather than a .sql file so it can read MARIADB_USER:
# hardcoding the username would silently stop matching the moment .env changes.
set -eu

: "${MARIADB_USER:?MARIADB_USER must be set}"

mysql --protocol=socket -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
-- The app names tenant databases store_landlord / tenant_<ulid>; a wildcard on
-- the store_ prefix covers both without granting anything on system schemas.
GRANT ALL PRIVILEGES ON \`store\\_%\`.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

echo "[store-init] granted CREATE on store_% to ${MARIADB_USER}"
