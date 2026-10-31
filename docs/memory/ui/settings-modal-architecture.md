---
name: settings-modal-architecture
description: Site Settings modal architecture: file map, the categories and their cards, moving a card, adding a new category.
---

## Why is Site Settings a modal and not a screen?
- A setting is mostly needed mid-task; a page means leaving it. The old one-form screen (247 columns per save) became categories in a modal.

## Which files make it up?
```
Modal (main path)
  includes/settings/modal.php      shell: header button + empty dialog + extras box
  settings_pane.php                endpoint: GET ?pane=<cat> cards, POST saves, both JSON
  includes/settings/fragment.php   pg_settings_fragment() / pg_settings_apply()
  assets/js/backend.src.js         pgSettingsModal, window.pgOpenSettings(), pgInitInjected()
Page (no-JavaScript path; linked from the main menu's Site Settings row, main-menu-settings-row.md)
  settings_<category>.php          thin wrappers, one per category: init + PG_SETTINGS_KEY + screen.php
  includes/settings/screen.php     page flow: shell, toolbar, save bar, POST/redirect
Shared
  includes/settings/registry.php   the ONE list: categories, sections, keywords, tool groups
  system_tools.php                 the tool groups as cards; the dialog's list ends in one row to it (settings-system-tools.md)
  includes/settings/prep.php       GET preparation (every category runs all of it)
  includes/settings/<cat>.php      cards -> $pg_settings_cards (+ $pg_settings_modals, $pg_settings_scripts)
  includes/settings/<cat>.save.php what that category writes
```

## Which categories and cards exist?
- `registry.php` is the list; read it for the cards. Order: `general`, `appearance`, `features`, `seo`, `privacy` (Visitors and privacy), `languages`, `contact` (Communication), `security` (Accounts and sign-in), `firewall` (Firewall and HTTPS), `commerce` (Ecommerce), `accounting`, `api` (Integrations), `maintenance` (Updates and maintenance). Keys stay when a label changes (`#settings/<key>/…`, `settings_<key>.php` links work).
- `accounting`: ERP and E-Invoice cards from Ecommerce (#3819, `accounting.save.php`), E-Invoice hidden outside Turkey (`pg_edoc_settings_shown()`); the `erp_settings.php` cards from `includes/erp/settings_cards.php` (`erp/erp-settings-cards.md`).
- `seo`: Site Icon, Social Sharing, Structured Data cards (built in `prep.php`); AI Crawlers holds the AI bot switches; Product Feeds is in `commerce` (#3908).
- `privacy`: Visitor Tracking (`privacy_analytics.php`), Cookie Consent, Legal Documents (#3899).
- `api`: Google Reviews came from Communication; Related screens: `pg_settings_integration_links()`, gated per screen (#4067).
- `maintenance`: Update Channel, Cron Jobs, Backups, Alerts, Maintenance Mode, out of General (#3968; Backups #4020).
- General › Organization: the organization's identity on one card (`settings-organization-card.md`).
- Accounts and sign-in and Firewall and HTTPS: the cards moved in #3858 (`settings-security-cards.md`).
- Appearance: Dashboard role < 2 (`settings-dashboard-card.md`), Private Label role 0 (#R7DPR).

## How do you move a card?
- Two edits: the `sections` entry in `registry.php`, and moving the card block between `includes/settings/<key>.php` files.
- Column ownership follows the card: move the columns it writes between the `<key>.save.php` files too, otherwise the column is ownerless and never written.
- When moving to another category also add a row to `pg_settings_legacy_anchors()` (`settings-modal-entry-points.md`).

## How do you add a new category?
- Entry in `registry.php` + `includes/settings/<key>.php` + `<key>.save.php` + `settings_<key>.php` wrapper (for the page path), and a `case 'settings_<key>.php'` in `output_menu()`'s switch so the main menu's Site Settings row is marked on it (`main-menu-settings-row.md`).

## What does the page path do with a refused save?
- It redirects back and draws the cards from storage, so what was typed is lost there; the dialog keeps it (settings-invalid-vs-error). The cards are plain HTML, which `liveform::prepare()` cannot refill.

Related: `settings-card-save-rules.md`
