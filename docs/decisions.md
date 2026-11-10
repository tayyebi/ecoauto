# Decisions

This project documents *why* in the code, next to the code it affects. Every
non-obvious choice carries a comment beginning with `DECISION`:

```php
// DECISION (tenancy): one database per storefront account, not a shared table
// with an account_id column, so a forgotten where() cannot leak a neighbour.
```

The prefix makes the reasoning greppable:

```bash
grep -rn "DECISION" app resources/views config routes database public/css
```

Tags in use:

| Tag | Applies to |
| --- | --- |
| `tenancy` | account tree, resolution, isolation, provisioning |
| `impersonation` | who may act as whom, and the audit trail around it |
| `mobile-first` | layout order and breakpoint direction in CSS and markup |
| `no-javascript` | anything that would otherwise have needed script |
| `farsi-only` | locale, direction, numerals, dates, slugs |
| `white-label` | keeping the product generic |
| `infra` | the `./data` directory, no image builds, process model |
| `data` | seeding, the content snapshot, media handling |
| `security` | hashing, tokens, path traversal, CSRF, rate limits |

## The standing decisions

1. **Product is `store`**, white-label. Nothing assumes a brand; `ecoauto` is
   tenant #1 and the only tenant with seeded content.
2. **Hierarchical tenancy.** Accounts form a tree; only leaves host storefronts;
   owners and admins inherit their whole subtree.
3. **Impersonation is tiered and always audited.** Platform staff may impersonate
   anyone but another platform owner; account admins only users in their subtree.
   While impersonating, the actor's own privileges are dropped.
4. **One MySQL database per storefront account**, auto-provisioned by the app,
   with the server's data directory under `./data/mysql`.
5. **Farsi only.** `fa` locale, `dir="rtl"`, Persian numerals, Jalali dates.
6. **No JavaScript at all.** All interaction is server-rendered and form-based.
7. **Mobile first.** Base styles target phones; `min-width` queries add layout.
   There are no `max-width` queries in the project.
8. **Deployment is `git pull` then `docker-compose up -d`.** No CI, no image
   registry, no build pipeline. The checkout is bind mounted read-only, so a
   pull plus a restart is a release. Only the runtime image is fixed
   (`php:8.4-cli`), and the checkout is never baked into it.
   All mutable state lives under `./data`, which is git-ignored.
9. **One port, 9020, on both sides of the container.** Above 1024 so the
   container needs no privileges; identical inside and outside so there is no
   mapping to misremember. `bin/deploy --check` refuses to start when another
   service already owns the host port.
10. **URLs are preserved** exactly from the legacy site, trailing slashes included.
11. **Cart, checkout and a placeholder payment gateway** exist; the gateway
    charges nothing and is swappable through `PaymentGateway`.
12. **Boot is idempotent.** `store:boot` runs on every container start: wait for
    the database, migrate what is outstanding, seed only when the account tree
    is empty. A restart must never be a repair job.
13. **The checkout is one read-write bind mount.** Not read-only with named
    volumes for `vendor/`, `storage/` and `bootstrap/cache/`: Docker creates each
    mount point by `mkdir`-ing it inside the parent mount, which fails on a
    read-only parent (`mkdirat .../app/vendor: read-only file system`). The
    directories it writes into are git-ignored, so `git pull` stays clean.
14. **One install script.** `bin/bootstrap` does the first install on the host
    and, with `IN_CONTAINER=1`, runs as the container entrypoint. `bin/deploy`
    delegates to it rather than reimplementing the preflight. Two scripts that
    both know how to install are two scripts that eventually disagree.
15. **`database.migrations` is one table name, not a map.** Laravel reads it as
    `$migrations['table'] ?? $migrations`; a nested per-connection shape yields a
    null table and the repository emits `create table `` `. Per-connection paths
    live under `database.migration_paths` and are passed with `--path`.
16. **The grant covers both database prefixes.** The landlord database is
    `store_landlord`; tenant databases are `tenant_<ulid>`. They share no prefix,
    so a grant on `store_%` alone leaves every storefront unable to create its
    own schema — and that only surfaces the first time an account is provisioned.
17. **SQL heredocs are quoted and substituted with sed.** An unquoted heredoc
    expands backticks and `$(...)`, so a backtick inside a SQL *comment* runs as
    a command — the grants still applied, which is what made it look harmless.
18. **Scripts probe for the MySQL client.** MariaDB 11 has no `mysql` binary;
    the client is `mariadb`. Anything that shells out probes
    `command -v mariadb || command -v mysql` instead of assuming.
19. **The first operator is configuration.** `STORE_ADMIN_EMAIL` and
    `STORE_ADMIN_PASSWORD` name the seeded administrator, so an installation is
    not permanently attached to one hardcoded address.
20. **Tenant connection settings derive from the landlord connection.** The
    framework still exposes `database.connections.mysql` even when a project
    never defines it: it is Laravel's stock config, reading `DB_HOST` and
    `DB_USERNAME`. This project uses `STORE_DB_*`, so deriving from `mysql`
    silently produced `127.0.0.1` / `root` and every provision failed with
    "Connection refused" while MariaDB was healthy. Landlord is the same server
    as every tenant, so it is the only correct base — and an unresolvable
    landlord config now throws instead of falling back to a default that lies.
21. **`Model::on()` binds a query to a connection, not `$builder->setConnection()`.**
    `setConnection()` exists only on the model and on the migrator. An Eloquent
    builder has no such method, and its `__call` forwards unknown names to the
    base query builder, which throws `BadMethodCallException`. On a *model*,
    `setConnection()` mutates that instance and returns it — so a method
    declared to return a builder must not rely on it. `Model::on($name)` does
    both correctly: it pins the connection on the model so relations inherit
    it, then hands back a builder.
22. **The content seeder is idempotent.** It is reachable from the repair
    command an operator is told to re-run, so a second run must not be a
    failure. Everything upserts on its natural key (slug, handle, setting key);
    menu items have no natural key and are rebuilt inside the transaction.
23. **Names on a context object are verified, never assumed.** `ResolveAccount`
    called `setPlatformHost()` where the class exposes `markPlatformHost()`.
    The middleware runs on every web request, so a wrong name is not a bug in
    one feature: every route answered 500, including the front page.
24. **Diagnostics never go into the response body.** `php:8.4-cli` ships no
    `php.ini`, so the container ran PHP's compiled development defaults:
    `display_errors=STDOUT`, `log_errors=Off`, `output_buffering=0`. A response
    is a byte stream whose header block is committed by the first body byte, so
    one notice from any dependency turned every page into

        Cannot modify header information - headers already sent

    raised at `vendor/symfony/http-foundation/Response.php:328` — because the
    built-in server's SAPI (`cli-server`) is not in Symfony's CLI allowlist, so
    `sendHeaders()` still calls `header()` on an already-committed response.
    The warning then replaced the real `RuntimeException` in the log, which is
    why the first fault was invisible. Two layers fix it: the image copies
    `docker/zz-store.ini` into `conf.d` (so the console and the web path agree), and
    `public/index.php` sets `display_errors=0` before requiring the
    autoloader, because that autoloader eagerly includes `autoload.files`
    before Laravel's own error bootstrap can exist. `bin/bootstrap` warns when
    the ini is not in effect, so a stale image is visible instead of silent.
25. **composer.lock's dist URLs follow the resolver's repo map, not the
    package name.** A Composer name is not a git host path: `league/*` is
    hosted at `thephpleague/*`, `psr/*` at `php-fig/*`, `guzzlehttp/*` at
    `guzzle/*`, `nesbot/carbon` at `briannesbitt/Carbon`, and the `v` prefix
    is not consistent across releases (guzzle tags `7.16.1`). The lock was
    hand-generated with `github.com/{name}.git` and `zipball/v{version}`, so
    25+ archives 404'd and `composer install` died on every clean `git pull`
    deploy, leaving the vendor volume half-written. `make-lock.py` now imports
    the `REPOS` map from `resolve-deps.py` (one copy, never two), probes
    codeload for the tag with and without the `v` prefix, and exits non-zero
    rather than write a lock that cannot install. Every dist URL in the lock
    is verified reachable before it is committed — a redirect to codeload
    (303/302) counts, HTTP 200 for the archive itself counts, 404 never
    ships.
26. **An XML declaration is emitted from a Blade PHP block, never from an
    echo.** Blade compiles a template by running PHP's own lexer over it
    (`token_get_all`) and compiling only the inline-HTML chunks. `<?xml` is not
    special to that lexer: `short_open_tag` is `On` in PHP's compiled defaults
    (both php.ini files say `Off`), so with no php.ini in play `<?xml` is a
    short open tag, the `{!!` before it and the `!!}` after it land in different
    chunks, the echo is never compiled, and the raw Blade source is written
    into the compiled template — which is then a parse error and a 500 raised
    from `Filesystem::getRequire()`, the top frame of the trace. Blade lifts a
    PHP block out before the lexer runs (`storePhpBlocks`) and restores it
    afterwards, so a `<?xml` inside one is never lexed and the view compiles
    the same with `short_open_tag` either way. `docker/zz-store.ini` sets
    `short_open_tag = Off` as a second layer, and `SitemapTest` asserts over
    the template *sources* rather than over a rendered response, because the
    setting is `PHP_INI_SYSTEM`: a host that runs the tests with it off would
    otherwise pass no matter what the templates contain. The inline note in the
    template describes the block in prose rather than naming it, and for the
    same reason this entry is where the name belongs: `storePhpBlocks` runs
    *before* comments are stripped, so an `@php` quoted in a comment above the
    block would pair with the block's `@endphp` and swallow everything between
    them. Quoting the directive is safe in a file that is never compiled.
