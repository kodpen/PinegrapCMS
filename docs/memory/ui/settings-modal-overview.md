---
name: settings-modal-overview
description: Start file for the settings-modal-* files: Site Settings dialog (gear button, list, Search settings, pane, card drawer, Unsaved Changes), decisions, which file holds what, adding a card
---

## What is it?
- Site Settings is a `<dialog>` opened over any panel screen by the gear button in the header, beside the notification bell: a category list on the left under group headings, a "Search settings" box above it, one pane of cards beside it with one Save per pane. Each category also keeps its own page, `settings_<key>.php`, for links without JavaScript.
- The pane is fetched from `settings_pane.php` as JSON and painted by `pgSettingsModal` in `backend.src.js`. Roles 0-2 only.

## Which decisions bind it?
- How the categories are grouped, one place per setting, the links and keys that stay: `workflow/decisions-made-product-14.md`. Group headings in the list: `decisions-made-product-13.md`.
- No Site Settings row in the main menu: `decisions-made-panel-2.md` (`main-menu-settings-row.md`).
- What opens from a card is a drawer inside the dialog, never a second dialog: `decisions-made-panel-3.md`.

## What does a person see, and where is it written?
- The categories, their group headings, which card sits where, and the file map: `settings-modal-architecture.md`. The System tools row at the list's end: `settings-system-tools.md`.
- The gear button, links that open the dialog (`#settings/<pane>/<card>`, `pg_settings_link()`), old addresses and moved-card anchors: `settings-modal-entry-points.md`.
- Search settings and how a text names a card's place (`Site Settings › <category> › <card>`): `settings-modal-search.md`.
- Sizes at each width, the phone list drawer, what scrolls: `settings-modal-layout.md`.
- A card's drawer (head with Close, its own scroll, Escape order): `settings-modal-drawer.md`; what else a card may hand over (hidden Connect forms): `settings-modal-card-extras.md`.
- Unsaved Changes ("Close Without Saving", "Switch Without Saving", "Keep Editing"): `settings-modal-dirty-tracking.md`.
- Password managers: fields arrive locked until touched (`settings-modal-autofill-lock.md`); a value nobody typed is put back with "A password manager filled in a field nobody was editing…" (`settings-modal-autofill-restore.md`).
- No breadcrumb or back arrow to settings, the address while open, tools that return to the dialog: `settings-modal-navigation.md`.

## Which rules does the code keep?
- The endpoint: gate, JSON only, CSRF by hand, the re-read after save, a language change reloads, Safe Mode changes the scheme: `settings-modal-endpoint.md`.
- Markup arrives by AJAX: jQuery `.html()`, scripts inside the pane, initializers that take a scope, `setting_` field ids: `settings-modal-injected-markup.md`.
- Card markup, field widths, save rules: the `settings-card-*` files (`settings-card-save-rules.md` first). The Workspace categories and their gate: `settings-workspace-gate.md`.

## How is a card or a category added?
- A card: its section and keywords in `includes/settings/registry.php`, its markup in `includes/settings/<key>.php`, its columns in `<key>.save.php`; a script into `$pg_settings_scripts`, a drawer through `pg_settings_drawer()`. Moving one also takes a `pg_settings_legacy_anchors()` row and its place texts (`settings-modal-architecture.md`, `settings-modal-search.md`).
- A category: a registry entry with a `group`, the `<key>.php` and `<key>.save.php` pair, a `settings_<key>.php` wrapper and its help row (`settings-modal-architecture.md`).
- The search index reads a new field's label by itself; check its Turkish keywords keep the English terms (`settings-modal-search.md`).
