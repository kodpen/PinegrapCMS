---
name: settings-modal-architecture
description: Site Settings dialog: file map, the 13 categories under 4 headings, where moved cards went, moving a card, adding a category.
---

## Why is Site Settings a dialog and not a screen?
- A setting is mostly needed mid-task; a page means leaving it. The old one-form screen (247 columns per save) became categories in a dialog, regrouped in 2026-10 so each setting has one place (`workflow/decisions-made-product-14.md`).

## Which files make it up?
```
includes/settings/registry.php   the ONE list: categories, groups, sections, keywords, tool groups
includes/settings/modal.php      shell: header button, list with headings, search box, empty pane
settings_pane.php                GET ?pane=<cat> cards, ?search=1 index, POST saves; JSON
includes/settings/fragment.php   pg_settings_fragment() / pg_settings_apply()
includes/settings/search.php     search index (settings-modal-navigation.md)
includes/settings/prep.php       GET preparation (every category runs all of it)
includes/settings/<cat>.php      cards -> $pg_settings_cards (+ modals, scripts)
includes/settings/<cat>.save.php the columns that category writes
settings_<cat>.php + screen.php  page path (no JavaScript; main menu's Site Settings row)
system_tools.php                 the tools that leave the dialog (settings-system-tools.md)
assets/js/backend.src.js         pgSettingsModal, window.pgOpenSettings(), pgInitInjected()
```

## Which categories exist, and in what order?
- Site: `general`, `appearance`, `features`, `seo` (SEO), `privacy` (Visitors and privacy), `languages`, `contact` (Communication). Security: `security` (Accounts and sign-in), `firewall` (Firewall and HTTPS). Selling: `commerce` (Ecommerce), `accounting`. Running the site: `api` (Integrations), `maintenance` (Updates and maintenance), then the System tools row. Headings: `settings-list-headings.md`.
- Keys stay when a label changes, so `#settings/<key>/…` and `settings_<key>.php` links keep working; `tests/settings_deep_links_test.php` fails on a link to a card its category does not list.

## Where did the moved cards go?
- General › Organization holds the organization's identity (`settings-organization-card.md`).
- Accounts and sign-in, Firewall and HTTPS: `settings-security-cards.md` (encryption key from Payment Methods).
- `seo`: Site Icon, Social Sharing, Structured Data (from `prep.php`), AI Crawlers; Product Feeds went to `commerce`.
- `privacy`: Visitor Tracking (`privacy_analytics.php`), Cookie Consent, Legal Documents.
- `accounting`: ERP, E-Invoice (hidden outside Turkey, `pg_edoc_settings_shown()`), the `erp_settings.php` cards (`erp/erp-settings-cards.md`).
- `api`: Google Reviews (from Communication), Related screens (`pg_settings_integration_links()`).
- `maintenance`: Update Channel, Cron Jobs, Backups, Alerts, Maintenance Mode (out of General).
- Appearance: Dashboard for role < 2 (`settings-dashboard-card.md`), Private Label for role 0 (`private_label.php` redirects to it).

## How do you move a card?
- Its `sections` and `keywords` entry in `registry.php`; its block between `includes/settings/<key>.php` files; its columns between the `.save.php` files (otherwise no save writes them, `settings-card-save-rules.md`); its list fields under `lists`; a `pg_settings_legacy_anchors()` row (`settings-modal-entry-points.md`); the help mapping in `get_help_url.php` when the category's topic changes.

## How do you add a category?
- A `registry.php` entry with a `group`, `includes/settings/<key>.php`, `<key>.save.php`, a `settings_<key>.php` wrapper, a `case 'settings_<key>.php'` in `output_menu()` (`main-menu-settings-row.md`) and a `settings_<key>` help row.

## What does the page path do with a refused save?
- It redirects back and draws the cards from storage, so what was typed is lost there; the dialog keeps it. The cards are plain HTML, which `liveform::prepare()` cannot refill.
