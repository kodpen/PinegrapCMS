---
name: main-menu-settings-row
description: The Site Settings row of the main menu (slot 20): two levels of panels, the page path instead of the modal (data-pg-settings-page), the gates it shares with the modal, the rows it marks active
---

## What does the Site Settings row show?
- The last row of the menu, slot 20 (it was left empty while the settings lived only in the modal). First level: only the categories of `pg_settings_categories()`, each opening a second-level panel with its page and its sections (`settings_<cat>.php#pgset-…`). The tool groups have their own System tools row, slot 25 (#3929, `settings-system-tools.md`).
- Built by `pg_menu_settings_entries()` (reads the registry) and `pg_menu_settings_markup()` (pure, `tests/menu_panel_test.php`) in `includes/fn/output.php`. The row's own address is the first category's page, for a middle click.

## Why does it not open the modal?
- The owner wanted the menu to lead to the settings pages (the no-JavaScript path, `includes/settings/screen.php`), the modal staying on the gear in the header. `pgSettingsModal` answers any link to `settings_<cat>.php` with the modal (`fromUrl()`), so every link of the row and its panels to a settings page carries `data-pg-settings-page`, and `asked()` returns null for it. The loading curtain asks the same `asked()`, so it shows on those links as on any navigation.
- On a settings page `fromHash()` leaves a `#pgset-…` that names a card of the page alone (many of them are also old-link keys in the anchor map), so a section link lands on the page and a same-page one scrolls; on a hashchange `markMenuCurrent()` runs again.

## Who sees it, and which entries?
- The same people and the same categories as the modal's sidebar: the row only for role <= 2 (`pg_settings_modal_markup()` and the pages' `validate_area_access($user, 'manager')`). No gate is written a second time in the menu.

## What else does a category panel list?
- Under its sections and a divider, the screens that go with the category without being settings pages: `pg_settings_category_screens($user)` (registry), each under its screen's gate. Translations under Languages and Translation (role <= 2). Private Label Branding is a section of Appearance since #4095 (role 0). They carry no `data-pg-settings-page`. Why: `main-menu-entry-gates.md`.

## Which screens mark it active?
- Row 20: the category pages (`settings_accounting.php` and `settings_privacy.php` included) and their screens (`translations.php`; `private_label.php`, now a redirect to the Appearance card), in `output_menu()`'s switch; a new one needs its `case` there.
- Row 25: `system_tools.php` and the tool screens (`view_log.php`, `smtp_settings.php`, `backups.php`, ...); the Store Lists screens for role <= 2, the shipping row (15) for role 3. `view_sessions.php` stays unmarked: it is in the Users panel too.

## How do the two levels behave?
- A second-level panel's `data-pg-menu-row` is the first-level entry's id (`menu_entry_20_<cat>`); `backMenuPanel()` sees that the entry sits in a panel and opens that panel (sliding in from the left, `.pg-menu-returning`) instead of the rows.
- The kept panel (`pgMenuPanel`) may be a second-level one: on `settings_general.php` the General panel opens again, its section marked when the address carries its `#pgset-…`.

Related: docs/memory/ui/main-menu-panels.md, docs/memory/ui/settings-modal-entry-points.md
