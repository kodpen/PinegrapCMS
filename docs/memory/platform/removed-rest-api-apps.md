---
name: removed-rest-api-apps
description: History note: apps.php, apps_settings.php and the custom_apps table were removed in 2026.4.4 and replaced by the external API (integration.php, includes/api/).
---

## What happened to apps.php / apps_settings.php / custom_apps?
- Removed in 2026.4.4: the `clean_up.php` deletion list + `install_drop_table('custom_apps')` (see `includes/migrations/2026.4.4.php`). Do not look for them.
- Replacement: the external API. Entry file `integration.php`; code in `includes/api/` (auth, scopes, ratelimit, router, resources/, outbound/); panel screens `api_settings.php` / `api_docs.php`.
- Current authentication lives in `includes/api/auth.php` (there is no apps.php validation flow any more). For the authorization model see the section "Dış API yetki kalıbı (`integration.php`, 2026.4.4)" (external API authorization pattern).

## What was the old design and why was it replaced?
- Old flow (for comparison only): per-app `custom_apps.api_key` + per-USER `user.secret_key` (AES-256-CBC, deterministic lookup via `secret_key_hash`); permissions via `has_permission($permissions, $action, 'read|edit')`.
- Flaw: the two keys belonged to two different entities, so any person's secret opened any app's key, and there was no rotation. The new API therefore moved key and secret onto the app.
- Owner decision: the `user.secret_key*` columns were deliberately left in the account table (dropping columns was not worth the risk); no code reads them any more.
