---
name: settings-system-tools
description: The System tools screen (system_tools.php): the screens beside Site Settings as cards, the dialog's one row to it, the palette rows; gates from pg_settings_tool_groups()
---

## What is the System tools screen?
- `system_tools.php` draws `pg_settings_tool_groups($user)` (`includes/settings/registry.php`: Monitoring, Connections, Store Lists, Jobs, Infrastructure) as one card per group, with `pg_settings_tool_cards()`. A group with no link is not drawn.
- Owner, 2026-10-30 (plan settings-recategorization, R1, PR #3795): people got lost in Site Settings, whose list mixed the categories with 18 links that left the dialog. The links moved here; the dialog's list keeps only categories.

## Who may open it?
- `validate_area_access($user, 'manager')`, the dialog's own gate (role <= 2). A user for whom `pg_settings_tool_count()` is 0 gets the usual "Access denied".
- Each link keeps the gate it has in `pg_settings_tool_groups()` (Repair Software for role 0, Cloudflare when configured, Store Lists with `USER_MANAGE_ECOMMERCE` and ecommerce on, Performance Log while monitoring is on, Reinstall while `install/` exists, no update/repair on a hosted site). Add or gate a tool there and the screen, the dialog row and the palette follow.

## What does a link keep?
- Its `target` (System Informations opens in a new tab, `rel="noopener"`) and its `confirm` as `data-confirm-content`, asked by the panel's global link handler in `backend.src.js` (Purge Cache).

## What does the dialog show?
- `pg_settings_tools_link($groups)` prints one `.pg-sm-link.pg-sm-away` row, "System tools ›", at the end of the list, or nothing when the user has no tool. There is no `.pg-sm-group` heading in the list any more.

## What does the search palette list?
- `pg_panel_search_tool_actions()` (`includes/panel/search.php`) adds the System tools screen and every tool from the same groups, inside the palette's manager block. Search words per script are in `pg_panel_search_tool_keys()`, Turkish and English; the label is always a key too.
- A tool with a `confirm` opens `system_tools.php`, where the question is asked: the palette follows a row without asking. A screen the palette already lists (Currencies) is not repeated. No tool, no rows.

## What is still to do?
- R1b, after #3141 (which holds the files): a System tools row under the main menu's System heading (`includes/fn/output.php`, where `pg_menu_settings_entries()` still lists the tool groups, so nothing goes missing meanwhile) and a help row for the screen in `get_help_url.php`; until then the screen passes `'help' => 'site-settings#site-settings-intro'` itself.

## Where is it tested?
- `tests/settings_system_tools_test.php`: no group heading in `modal.php`, the row only with tools, every administrator tool on the screen with its confirm, the palette's tools and gates.
