---
name: addon-hook-system-census
description: Add-on hook system AH1 census from the code: announced events, driver registries, UI slot places, what integrity, backup, System Status and the migration runner read
---

## What is this census?
- Step AH1 of `addon-hook-system.md` (with `addon-hook-system-site.md`): what the code has today, written for the architect's design note. It describes, it does not design. Paths are relative to `pinegrap/`.
- Parts: this file (the plan's hook points, open questions), `addon-hook-system-census-events.md`, `addon-hook-system-census-drivers.md`, `addon-hook-system-census-slots.md`, `addon-hook-system-census-print.md`, `addon-hook-system-census-files.md`. Open questions are at the end of this file.

## How do the plan's hook points map onto the code?
- Order placed, order status changed, product saved: covered by `order.created`, `order.status_changed`, `product.created`/`product.updated` (several doors, above).
- Page published: no publish event; `page.created` (`add_page.php`, API design) and `page.updated` (API pages/design, Workspace) are what exists.
- User signed in: no event. The session is made in `pg_session_sign_in($user_id, $username)` (`includes/authentication.php`).
- Settings saved: no event. Each category's `includes/settings/<key>.save.php` is included by `includes/settings/screen.php` and `includes/settings/fragment.php`, after `pg_audit_fetch('config')`.
- Cron tick: no event; the job runners are in `addon-hook-system-census-drivers.md`.
- No `pg_hook()` / `pg_hook_filter()` exists yet; the nearest seam is `api_module_contributions()`, a fixed list of modules each declaring `<prefix>api_routes`, `webhook_events`, `openapi_objects`, `scope_groups`, `owner_scopes`.
