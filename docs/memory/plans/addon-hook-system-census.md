---
name: addon-hook-system-census
description: Add-on hook system AH1 census from the code: announced events, driver registries, UI slot places, what integrity, backup, System Status and the migration runner read
---

## What is this census?
- Step AH1 of `addon-hook-system.md` (with `addon-hook-system-site.md`): what the code has today, written for the architect's design note. It describes, it does not design. Paths are relative to `pinegrap/`.
- Parts: this file (the plan's hook points, open questions), `addon-hook-system-census-events.md`, `addon-hook-system-census-drivers.md`, `addon-hook-system-census-registries.md`, `addon-hook-system-census-slots.md`, `addon-hook-system-census-print.md`, `addon-hook-system-census-files.md`. Open questions are at the end of this file.

## How do the plan's hook points map onto the code?
- Order placed, order status changed, product saved: covered by `order.created`, `order.status_changed`, `product.created`/`product.updated` (several doors: `addon-hook-system-census-events.md`).
- Page published: no publish event; `page.created` (`add_page.php`, API design) and `page.updated` (API pages/design, Workspace) are what exists.
- User signed in: no event. The session is made in `pg_session_sign_in($user_id, $username)` (`includes/authentication.php`).
- Settings saved: no event. Each category's `includes/settings/<key>.save.php` is included by `includes/settings/screen.php` and `includes/settings/fragment.php`, after `pg_audit_fetch('config')`.
- Cron tick: no event; `job.php` and `pg_cron_jobs()` are in `addon-hook-system-census-registries.md`.
- No `pg_hook()` / `pg_hook_filter()` exists yet; the nearest seam is `api_module_contributions()`, a fixed list of modules each declaring `<prefix>api_routes`, `webhook_events`, `openapi_objects`, `scope_groups`, `owner_scopes`.

## Open questions?
- One door for hooks: `pg_announce()`, `api_webhook_enqueue()` called directly and `erp_event()` each announce; a hook in one misses the others. Which one carries the add-on hook, or do the direct calls move to `pg_announce()` first?
- Event names an add-on announces: `api_module_contributions()` takes a fixed module list; does an add-on join it, or get its own merge?
- Payment add-ons: a new card gateway or redirect method needs an ENUM value appended by a migration step, which an add-on's own migration would have to do on a core column.
- Marketplace, SMS, e-document, jobs: no register function, and marketplace connectors and SMS load only from their own folder; each needs a register function or a loader change.
- Migrations: the runner records only `config.version`; an add-on's steps need a done record of their own (where: a column of the planned `addons` table, or a config row).
- Integrity: add-on files are already invisible to the check (it walks the release list only), so "ignores `includes/addons/`" needs no change unless first-party add-ons ship in the release.
- Backup: no backup holds code, so add-on folders are not backed up; the plan's "backups include them" asks for a file part the backup does not have today.
- Restore leaves an add-on table not in `get_tables()` with its old rows.
- UI slots: the orders list's toolbar is hand-written (not `pg_screen_toolbar()`), and its bulk actions go through a JavaScript `switch` and `edit_orders.php`; a bulk slot touches all three. Print Order's totals and foot are inline.
