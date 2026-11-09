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
