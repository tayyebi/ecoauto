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
8. **No container images.** A base PHP runtime serves the mapped sources and all
   mutable state lives under `./data`, which is git-ignored.
9. **URLs are preserved** exactly from the legacy site, trailing slashes included.
10. **Cart, checkout and a placeholder payment gateway** exist; the gateway
    charges nothing and is swappable through `PaymentGateway`.
