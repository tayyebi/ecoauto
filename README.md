# store

A white-label, multi-tenant storefront platform. Server-rendered Blade, no
JavaScript, one MySQL database per storefront account, and a hierarchical
account tree with impersonation.

`www.ecoauto.ir` is tenant #1 and its content is seeded from the legacy Hugo
site, but nothing in the code is specific to it.

## Deploying

The workflow is `git pull` and `docker-compose up -d` on the target server.
There is no CI and no image registry — the checkout *is* the deployment.

```sh
git pull
./bin/deploy
```

`./bin/deploy` is a thin wrapper around `docker-compose up -d` that does the
things a hand-run deploy usually forgets:

1. creates `.env` from `.env.example` if it is missing
2. generates `APP_KEY` with openssl, so sessions and CSRF work
3. refuses to start when the two database passwords are still empty
4. **checks that port 9020 is free on this host** and stops with a clear message
   if another service already owns it (`./bin/deploy --check` tests this alone)
5. syncs `STORE_PORT` and `APP_URL` in `.env`, then runs `docker-compose up -d`

Plain `docker-compose up -d` also works once `.env` exists; it just skips the
preflight checks.

### Ports

The app binds **0.0.0.0:9020 inside the container** and publishes **9020 on the
host** — the same number on both sides, so there is never a `80:9020` mapping to
misread. 9020 is above 1024, so the container needs no root and no
`NET_BIND_SERVICE` capability to listen.

If something on your server already holds 9020, nothing is started and the
script tells you what owns it. Point the stack at a free port instead:

```sh
./bin/deploy --port 9123
```

Because compose maps `${STORE_PORT}:${STORE_PORT}`, one variable moves both
ends and they cannot drift apart.

To run somewhere else, pick a free port and keep the two sides in sync:

```sh
./bin/deploy --port 9123
```

Compose maps `${STORE_PORT}:${STORE_PORT}`, so one variable moves both ends.

### What happens on every start

`bin/container-entrypoint` runs before the server. The checkout is mounted
read-only, so the entrypoint only ever writes to `./data`, the `storage` volume
and the `bootstrap-cache` volume. It runs `composer install` when `composer.lock`
is newer than the installed vendor tree, creates the writable directories, then
runs `php artisan store:boot --seed`:

- waits for MariaDB to accept connections (retries, so a slow db container does
  not break the deploy)
- runs any outstanding landlord migrations
- seeds the first tenant **only if the account tree is empty**
- warns about any path the container cannot write

Every step is idempotent. Restarting the stack is a no-op, not a repair.

## Data

Everything stateful lives under `./data`, which is git-ignored:

| path | contents |
| --- | --- |
| `data/mysql` | MariaDB data files, including every tenant database |
| `data/media` | uploaded media, one directory per account |
| `data/sessions`, `data/cache`, `data/logs` | runtime state |
| `data/backups` | dumps from `./bin/db-backup` |

Stop the stack, tar `./data`, and you have a complete backup. `./bin/db-backup`
dumps every database the installation owns — the landlord one plus one per
tenant, which are created lazily and so cannot be listed in advance.

```sh
./bin/db-backup
gunzip -c data/backups/store-<stamp>.sql.gz | docker compose exec -T db mysql -u root -p
```

## Operating

```sh
./bin/deploy --logs      # tail the app
./bin/deploy --down      # stop, keep ./data
./bin/deploy --restart   # recreate the app container only
```

The control plane is at `/admin`. The seeded operator is `admin@ecoauto.ir` /
`password` — change it immediately on a public host.

## Local development without Docker

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan store:install   # creates ./data, the schema and tenant #1
php artisan store:serve     # http://localhost:9020
```

## Layout

```
app/Tenancy/            account tree, host resolution, database provisioning
app/Http/Controllers/   storefront and admin controllers
app/Services/           cart, payments, audit, impersonation
resources/views/        Blade views, mobile-first
public/css/             the entire styling: store.css and admin.css
database/migrations/    landlord/ and tenant/ schemas
bin/                    deploy, entrypoint, backup
docs/decisions.md       why the code looks the way it does
```

## Design decisions

Non-obvious choices carry an inline `DECISION (tag): why` comment so the reason
sits next to the code it constrains. `docs/decisions.md` explains the
convention and lists the standing ones.

```sh
grep -rn "DECISION" app/ config/ routes/ database/ public/css/
```
