---
name: external-api-authorization
description: How integration.php (external API) computes effective scopes: app scopes x owner-delegable scopes, write implies read in one place, removed apps.php model.
---

## How is external API authorization decided? (integration.php, 2026.4.4)
- Authorization passes two gates, both defined in one place, `includes/api/scopes.php`:
  1. The app's scopes + the write->read rule (`api_scopes_expand`).
  2. What the owner user can delegate today (`api_owner_scopes`).
- Effective authority = the intersection of the two, computed on EVERY request, never stored:
```php
$app['scopes'] = api_effective_scopes($granted, $owner);
```

## How are scope names and the write->read rule handled?
- Scope names are `resource:read` / `resource:write` (`kaynak:read` / `kaynak:write`).
- `write` implies `read`, and that rule lives ONLY in `api_scopes_expand()`; the database stores exactly what the operator selected.

## What can the owner delegate?
- Same rules as the panel (the `validate_ecommerce_access` pattern): role 0-2 exempt, role 3 needs `manage_ecommerce`.

## What was removed?
- The old `apps.php` model (per-app `api_key` + per-USER `secret_key`, `has_permission()` with `{action, type}` pairs) was removed in 2026.4.4.
