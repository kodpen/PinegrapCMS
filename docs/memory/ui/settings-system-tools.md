---
name: settings-system-tools
description: The System tools screen (system_tools.php): the screens beside Site Settings as cards, the dialog's one row to it, the palette rows; gates from pg_settings_tool_groups()
---

## What is the System tools screen?
- `system_tools.php` draws `pg_settings_tool_groups($user)` (`includes/settings/registry.php`: Monitoring, Connections, Store Lists, Jobs, Infrastructure) as one card per group, with `pg_settings_tool_cards()`. A group with no link is not drawn.
- Owner, 2026-10-30 (Site Settings regrouping, `workflow/decisions-made-product-14.md`; PR #3795): people got lost in Site Settings, whose list mixed the categories with 18 links that left the dialog. The links moved here; the dialog's list keeps only categories.

## Who may open it?
- `validate_area_access($user, 'manager')`, the dialog's own gate (role <= 2). A user for whom `pg_settings_tool_count()` is 0 gets the usual "Access denied".
- Each link keeps the gate it has in `pg_settings_tool_groups()` (Repair Software for role 0, Cloudflare when configured, Store Lists with `USER_MANAGE_ECOMMERCE` and ecommerce on, Performance Log while monitoring is on, Reinstall while `install/` exists, no update/repair on a hosted site). Add or gate a tool there and the screen, the dialog row and the palette follow.

## What does a link keep?
- Its `target` (System Informations opens in a new tab, `rel="noopener"`) and its `confirm` as `data-confirm-content`, asked by the panel's global link handler in `backend.src.js` (Purge Cache).

## What does the dialog show?
- `pg_settings_tools_link($groups)` prints one `.pg-sm-link.pg-sm-away` row, "System tools ›", at the end of the list, or nothing when the user has no tool. There is no `.pg-sm-group` heading in the list any more.

## What does the search palette list?
- `pg_panel_search_tool_actions()` (`includes/panel/search.php`) adds the System tools screen and every tool from the same groups, inside the palette's manager block. Search words per script are in `pg_settings_tool_keys()` (`includes/settings/registry.php`), Turkish and English; the label is always a key too. The Site Settings search finds the same tools (`settings-modal-search.md`, #4303).
- A tool with a `confirm` opens `system_tools.php`, where the question is asked: the palette follows a row without asking. A screen the palette already lists (Currencies) is not repeated. No tool, no rows.

## Where is it in the main menu?
- Row 25 under the System heading, after Site Settings (#3929). Slot 25 because 3, 13, 18 and 19 are retired and 22-24 taken; operators pin rows by slot, so it is never reused or renumbered.
- Gate: `$user['role'] <= 2 && pg_settings_tool_count(pg_settings_tool_groups($user)) > 0`, the screen's own. Entries from `pg_menu_tools_markup()` (pure): the screen first, a divider, then one entry per non-empty group (`menu_entry_25_tools_<n>`) opening a second-level panel of its screens (`menu_panel_25_tools_<n>`), target, `rel="noopener"` and `data-confirm-content` kept.
- `system_tools.php` and every tool screen mark row 25 (`output_menu()`'s switch); Store Lists screens mark it for role <= 2, the shipping row for role 3.

## What is its help topic?
- The `system_tools` row of `get_help_url.php`, `site-settings#system-tools`; the screen passes no `'help'`. The section is proposed to the owner; until it exists the link lands on the page top.

## Where is it tested?
- `tests/settings_system_tools_test.php`: no group heading in `modal.php`, the row only with tools, every administrator tool on the screen with its confirm, the palette's tools and gates.
