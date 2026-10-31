---
name: settings-list-headings
description: Site Settings dialog list: the four group headings (decided #3879, built #4118), the group key per category, how a new category joins one.
---

## Which headings sit over the category list?
- Four, from `pg_settings_groups()` in `includes/settings/registry.php`: Site (General, Appearance, Features, SEO and sharing, Visitors and privacy, Languages and translation, Communication), Security (Accounts and sign-in, Firewall and HTTPS), Selling (Ecommerce, Accounting), Running the site (Integrations, Updates and maintenance, then the System tools row).
- Why: thirteen rows and a link without landmarks were hard to scan, most of all in the phone drawer (decided #3879, built #4118).

## How are they drawn?
- `modal.php` loops over `pg_settings_grouped(pg_settings_categories())` and prints `<div class="pg-sm-group">` over each group's rows: a label like the main menu's, not a link, no fold. The System tools row (`pg_settings_tools_link()`) follows the last group.
- `pg_settings_grouped()` skips a heading with no row the reader may open and keeps one over a single row; a category with no known `group` goes under the last heading instead of leaving the list.

## How does a new category join a group?
- Give it a `group` key (`site`, `security`, `selling`, `running`) in its `registry.php` entry. The groups follow the list's order, so put the entry among its group's neighbours: no category moved and no `#settings/<key>/<card>` address changed when the headings came.
- The tool groups (Monitoring, Connections …) are not these headings: they stay on the System tools screen (`settings-system-tools.md`).
