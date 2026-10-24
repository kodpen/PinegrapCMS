---
name: main-menu-settings-row
description: The Site Settings row of the main menu (slot 20): two levels of panels, the page path instead of the modal (data-pg-settings-page), the gates it shares with the modal, the rows it marks active
---

## What does the Site Settings row show?
- The last row of the menu, slot 20 (it was left empty while the settings lived only in the modal). First level: every category of `pg_settings_categories()`, then, under a divider, every group of `pg_settings_tool_groups()` (Monitoring with Site Log and Sessions, Connections, Store Lists, Jobs, Infrastructure; the store group is not called Ecommerce, which is the category above it). Each entry opens a second-level panel: a category its page and its sections (`settings_<cat>.php#pgset-…`), a group its screens.
- Built by `pg_menu_settings_entries()` (reads the registry) and `pg_menu_settings_markup()` (pure, `tests/menu_panel_test.php`) in `includes/fn/output.php`. The row's own address is the first category's page, for a middle click.

## Why does it not open the modal?
- The owner wanted the menu to lead to the settings pages (the no-JavaScript path, `includes/settings/screen.php`), the modal staying on the gear in the header. `pgSettingsModal` answers any link to `settings_<cat>.php` with the modal (`fromUrl()`), so every link of the row and its panels to a settings page carries `data-pg-settings-page`, and `asked()` returns null for it. The loading curtain asks the same `asked()`, so it shows on those links as on any navigation.
- On a settings page `fromHash()` leaves a `#pgset-…` that names a card of the page alone (many of them are also old-link keys in the anchor map), so a section link lands on the page and a same-page one scrolls; on a hashchange `markMenuCurrent()` runs again.

## Who sees it, and which entries?
- The same people and the same entries as the modal's sidebar, from the same two functions: the row only for role <= 2 (`pg_settings_modal_markup()` and the pages' `validate_area_access($user, 'manager')`), each tool under its own gate inside `pg_settings_tool_groups($user)` (Repair Software for role 0, Cloudflare for role < 1 with its keys, the Store Lists group with `USER_MANAGE_ECOMMERCE`, ...). No gate is written a second time in the menu.

## Which screens mark it active?
- The category pages and the screens of its tool panels (`view_log.php`, `smtp_settings.php`, `backups.php`, ...), in `output_menu()`'s switch; a new category or tool screen needs its `case` there. `view_sessions.php` stays unmarked: it is in the Users panel too.

## How do the two levels behave?
- A second-level panel's `data-pg-menu-row` is the first-level entry's id (`menu_entry_20_<cat>`); `backMenuPanel()` sees that the entry sits in a panel and opens that panel (sliding in from the left, `.pg-menu-returning`) instead of the rows.
- The kept panel (`pgMenuPanel`) may be a second-level one: on `settings_general.php` the General panel opens again, its section marked when the address carries its `#pgset-…`.

Related: docs/memory/ui/main-menu-panels.md, docs/memory/ui/settings-modal-entry-points.md
