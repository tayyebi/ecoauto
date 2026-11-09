# store

A white-label, multi-tenant storefront platform. Server-rendered Blade, no
JavaScript, one MySQL database per storefront account, and a hierarchical
account tree with impersonation.

`www.ecoauto.ir` is tenant #1 and its content is seeded from the legacy Hugo
site, but nothing in the code is specific to it.

## Deploying

The workflow is `git pull` and `docker-compose up -d` on the target server.
There is no CI and no image registry — the checkout *is* the deployment.

### First install

```sh
git pull
./bin/bootstrap
```

`./bin/bootstrap` is the whole first install, in order:

1. checks Docker and Compose are installed
2. **checks port 9020 is free** and names whatever process holds it if not
3. creates `.env` from `.env.example`
4. generates `APP_KEY`, `STORE_DB_PASSWORD` and `STORE_DB_ROOT_PASSWORD`
5. creates the `./data` tree
6. builds and starts the stack
7. waits for `/admin/login` to answer (a first install compiles dependencies, so
   this is minutes, not seconds)
8. prints the admin URL and the seeded credentials

`./bin/bootstrap --check` runs steps 1–2 only and changes nothing.
`./bin/bootstrap --port 9123` installs on a different port.

### Later deploys

```sh
git pull && ./bin/deploy
```

`./bin/deploy` delegates to `bin/bootstrap` for the install path and adds the
day-to-day verbs:

```sh
./bin/deploy --logs      # tail the application
./bin/deploy --down      # stop the stack, keep ./data
./bin/deploy --restart   # recreate the app container only
```

There is exactly one install implementation; `deploy` does not reimplement the
preflight.

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

The same `bin/bootstrap` script runs as the container entrypoint, told apart by
`IN_CONTAINER=1`. The checkout is bind mounted read-write, so `vendor/`,
`storage/` and `bootstrap/cache/` are real paths inside it — all three are
git-ignored, so a `git pull` never conflicts with them. It runs `composer
install` when `composer.lock` is newer than the installed vendor tree, creates
the writable directories, then runs `php artisan store:boot --seed`:

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
./bin/db-backup          # writes data/backups/store-<stamp>.sql.gz
./bin/db-restore         # restores the newest dump
./bin/db-restore <file>  # or a specific one
```

Restore is a script rather than a documented `gunzip | docker compose exec …mysql -u root -p`
pipeline: that prompts for a password the container already holds, which is how
people end up pasting secrets into a shell.

### Grants

The application creates one database per storefront account at runtime, so its
MySQL user needs `CREATE` on the `store_` prefix. That is granted by
`bin/mysql-init.sh`, which MariaDB runs once when it initialises an *empty* data
directory — so an installation whose database already exists never gets it.
Apply it at any time with:

```sh
./bin/db-grant
```

## Operating

```sh
./bin/deploy --logs      # tail the app
./bin/deploy --down      # stop, keep ./data
./bin/deploy --restart   # recreate the app container only
```

The control plane is at `/admin`. The seeded operator is
`tayyebimohammadreza@gmail.com` / `password` — change it immediately on a public
host. Both are configurable through `STORE_ADMIN_EMAIL` and
`STORE_ADMIN_PASSWORD`.

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
