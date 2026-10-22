---
name: external-api-authorization
description: External API authorization: app scopes x owner scopes, write implies read, where a new scope goes, what failed sign-ins do, the removed apps.php model; role-3 rows in api-user-role-rows
---

## How is external API authorization decided? (integration.php, 2026.4.4)
- Authorization passes two gates, both defined in one place, `includes/api/scopes.php`:
  1. The app's scopes + the write->read rule (`api_scopes_expand()`).
  2. What the owner user can delegate today (`api_owner_scopes()`).
- Effective authority = the intersection of the two, computed on EVERY request, never stored, so demoting a user narrows their applications at once:
```php
$app['scopes'] = api_effective_scopes($granted, $owner);
```
- Devices and OAuth connections narrow it further: `api-device-sessions.md`.

## How are scope names and the write->read rule handled?
- Scope names are `resource:read` / `resource:write`.
- `write` implies `read`, and that rule lives ONLY in `api_scopes_expand()`; the database stores exactly what the operator selected.

## Where does a new scope go?
- Into `api_scope_groups()` in `includes/api/scopes.php`: a resource group with a `label` and its `read` and `write` names (`''` for a half the resource does not offer: inventory is write-only, meta read-only). The permission screen, the OAuth consent lines and `api_scopes_all()` read that list.
- And into its owner ceiling: `api_owner_scopes()` for a core scope; a module adds its groups and caps them through its seam (`erp_owner_scopes()`, `ws_owner_scopes()`, `translate_owner_scopes()`). `api_effective_scopes()` intersects the grant with that ceiling, so a scope missing from it answers 403 on every call.

## What can the owner delegate?
- What the panel would let it do (`api_owner_scopes()`): the store, contact, form, calendar, visitor and campaign scopes to roles 0-2, to role 3 only with the matching right (`manage_ecommerce`, `manage_contacts`, `manage_forms`, `manage_calendars`, `manage_visitors`, `manage_emails`); pages, files, SEO, comments and Site Log to roles 0-2; webhooks, design and system to roles 0-1.

## What does a failed sign-in do?
- An unknown key and a wrong secret get the same 401 (keys cannot be enumerated) and both write a firewall offence with `api_offence()`, as does a wrong client secret at the OAuth token endpoint. An unknown or expired device token is NOT an offence: a phone signed out from the panel would otherwise get its whole office banned.

## Who creates keys?
- Application keys, secrets and passwords are created, and credential boxes filled in, by the site's owner in the panel or the provider's console; never generated or typed in for them.

## Which rows does an owner with the user role (3) reach?
- The scope opens a kind of record; for role 3 each endpoint also narrows the rows as the panel screen does (404 outside): `api-user-role-rows.md`.

## What was removed?
- The old `apps.php` model (per-app `api_key` + per-USER `secret_key`, `has_permission()` with `{action, type}` pairs) was removed in 2026.4.4: `removed-rest-api-apps.md`.
