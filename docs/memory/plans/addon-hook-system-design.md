---
name: addon-hook-system-design
description: Design note of the add-on system (AH2): the folder and manifest, the hook list and its one door, the slot list, the registry and its table, what part one builds and what waits for the release
---

## What is an add-on on disk?
- A folder `includes/addons/<slug>/` (slug: a-z, 0-9, hyphen, 40 at most) with `addon.json` and `addon.php`. The registry includes `addon.php` of each enabled add-on once, after `functions.php`, never in `waf.php` and never on the install or update screens.
- `addon.json`: `name`, `version`, `min_pinegrap`, `hooks` (names from the list), `slots` (slot, label, callback), `settings` (fields drawn by the core, a secret field sealed), `tables` (its own tables, for backup and restore), `events` (names it announces, always `addon.<slug>.<name>`).
- A manifest that does not parse, names an unknown hook or slot, or asks a newer version leaves the add-on off with its reason shown; one add-on's fault never stops the page: `addon.php` and every callback run inside a guard that logs and switches the add-on off after three faults in an hour.

## Which hooks are there, and through which door?
- One door: `pg_announce()`. The direct `api_webhook_enqueue()` and `erp_event()` calls the census found move to it first, so webhooks and add-ons see the same moments.
- `pg_hook_on($name, $callback)` in `addon.php`; the core calls `pg_hook($name, $payload)`. Version one has actions only: every announced event by its name, and four new moments: `user.signed_in`, `settings.saved` (the category), `cron.tick`, `page.published`. A callback gets the event's payload as an array and returns nothing.
- No filters in version one: nothing an add-on returns changes a price, a total, a right or a query. A filter is added later one by one, each by its own decision.

## Which slots are there?
- `order.actions` (the three-dots menu), `order.card` (the card grid), `orders.row_action`, `orders.toolbar`, `print_order.above_items`, `print_order.below_items`, `print_order.foot`, `settings.card` (the add-on's own card in Site Settings › Features).
- A slot's callback gets the record's id and returns data, never markup for the panel: a label, an icon name, an address or a posted action, and for a card rows of label and value; the core draws it with look A. Only the three print slots return HTML, passed through the same cleaning as a rich text field.
- A posted action comes back to `addon_action.php` (a new root file, CSRF token, the add-on's `can` rule checked by the core), which calls the add-on's handler.
- The orders list's bulk bar and whole-screen replacement are not slots.

## Where is the state kept?
- Table `addons` (asked of the owner): `slug` (unique), `version`, `enabled`, `settings` (JSON, secrets sealed), `migrated` (the labels of its own migration steps already run), `faults`, `installed_at`, `enabled_by`. An add-on's migration file runs through the same helpers, its labels prefixed `addon.<slug>.`.
- Enable and disable: Site Settings › Features, an "Add-ons" card listing the folders found; Administrator and Designer only, written to the audit trail. Writing an add-on's code in the panel (part two) is no wider than writing a PHP region.

## What about integrity, backup and updates?
- An update never touches `includes/addons/`. The integrity check does not count it; System Status lists each add-on with its version and state.
- Backups hold no code today and still do not: an add-on's folder is carried by hand; its tables named in the manifest join the backup and restore lists.

## What does each part build?
- Before 2026.11.0 ships: the steps AH3 to AH8 of `addon-hook-system-steps.md`.
- After it ships: preparing an add-on in the panel, a zip upload with its signature check (#3344), driver families (payments, shipping, marketplaces, SMS, e-documents) registered by add-ons, the shipped drivers moved into add-on folders, the contract opened to others.
