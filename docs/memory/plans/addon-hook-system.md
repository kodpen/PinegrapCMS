---
name: addon-hook-system
description: Plan (approved 2026-10-19, watch list, not before 2026.11.0 ships): add-on packages under includes/addons/ with manifest and hooks so payment, carrier, marketplace and SMS drivers add without forking
---

## What did the owner decide?
- 2026-10-19: open this after the payment driver layer is in use; the driver interface is the first add-on contract. Nothing in the core is removed or moved to add-ons; add-ons only add.
- 2026-10-30 (#3344): not in 2026.11.0; it starts after that version ships, and new PSP, carrier and marketplace drivers keep landing in the core until then. Architect's notes for the start, re-read then: the hook list and manifest as below; enabled state and settings in an `addons` table (`slug`, `version`, `enabled`, `settings` JSON with secret fields sealed, `installed_at`, `enabled_by`) by `install_create_table()` in the then-open version; no panel zip upload before the signature check.

## Why?
- There is no hook/filter API (`do_action`-style) and no add-on packaging; extension today means PHP regions, per-page hook code, the API, webhooks and MCP. Every compared platform has a marketplace or plugin system; without one, every new PSP, carrier or marketplace lands in the core and in `submit_order.php`-style switch statements.

## What is decided?
- Scope is small and PHP-only: an add-on is a folder `includes/addons/<slug>/` with `addon.json` (name, version, min Pinegrap version, provides: `payment|shipping|marketplace|sms|edoc|job`, settings schema, hooks) and `addon.php` registering drivers through the existing interfaces (`includes/payments/driver.php`, `includes/shipping/`, connectors `base.php`, `includes/sms/`, `includes/erp/edoc/`). No Composer, no autoload magic: the registry includes the file.
- Hooks: a minimal `pg_hook($name, ...$args)` / `pg_hook_filter($name, $value, ...$args)` pair in `includes/fn/core.php` with a fixed, documented list of hook points (order placed, order status changed, product saved, page published, user signed in, settings saved, cron tick); events already announced to webhooks (`includes/fn/events.php`) fire the matching hook too, so add-ons and webhooks see the same moments. No hook may change express-order totals (filter list excludes them).
- Lifecycle: enable/disable per add-on in Settings › Features; an add-on's schema changes go through its own migration file run by the same runner (namespaced labels); file integrity ignores `includes/addons/` but lists installed add-ons in System Status; backups include them.
- Distribution: a zip upload in the panel (manager+) only together with its signature check (#3344); first-party add-ons ship inside the release (e.g., PayTR, Netgsm) so hosting without a marketplace works. A public catalogue is a later decision.
- Security: add-on code runs with full PHP rights, so the panel warns, logs enable/disable in the audit trail and the WAF/CSP settings stay core-only.

## What remains?
- Design note with the hook list and manifest schema (owner review).
- Slices: registry + manifest + enable/disable (M), hooks at the listed points (S–M), migration namespacing (S), System Status + backup (S), zip upload + signature check (S–M), first conversions of the shipped PayTR/Netgsm drivers into add-on folders (S, no behaviour change).

## Open questions?
- Should add-ons be allowed to add panel menu items and settings cards? Yes, through the manifest, rendered by core templates only.

## Where to start?
- After 2026.11.0 ships (owner, #3344); the payment driver layer is done (`commerce/payment-driver-layer-decisions.md`). Read `includes/fn/events.php` and `core/file-placement-rules.md`, `core/file-header-and-embedded-libraries.md` (gate constants, header block).
