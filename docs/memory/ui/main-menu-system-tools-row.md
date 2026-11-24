---
name: main-menu-system-tools-row
description: Main menu row 25, System tools: its slot and gate, its panel as one list of every tool under group headings (pg_menu_tools_markup()), the screens that light it
module: output
---

## Where does the row sit and who gets it?
- Row 25 under the System heading, after Users, last (#3929). Slot 25 because 3, 13, 18 and 19 are retired and 22-24 taken; operators pin rows by slot, so it is never reused or renumbered.
- Gate: `$user['role'] <= 2 && pg_settings_tool_count(pg_settings_tool_groups($user)) > 0`, the screen's own. Its panel is one list from `pg_menu_tools_markup()` (pure, returns the entries): per non-empty group a heading (`.pg-menu-panel-heading`, not a link), then its tools, target, `rel="noopener"` and `data-confirm-content` kept. No entry for the screen and no second-level panels (#11247).
- Owner (2026-11-22, poll 220, `workflow/decisions-made-panel-17.md`): every tool directly under the row, the groups' names as headings; the screen stays, opened from Site Settings' "System tools" row, the search and the row's own address (a middle click). A group with no tool for the person prints no heading.
- `output_menu()` defines `PG_SETTINGS_MENU` and includes `registry.php` itself before the call: the registry is not a function module, and without it every panel screen but the settings ones died with an undefined-function 500 for roles <= 2 (#5158, `tests/menu_settings_registry_test.php`).
- `system_tools.php` and every tool screen mark row 25 (`output_menu()`'s switch); Store Lists screens mark it for role <= 2, the shipping row for role 3.

## How is it tested?
- `tests/menu_panel_test.php`: the one list (a heading per group with tools, no screen entry, no second level, targets, confirms, escaping). `tests/settings_system_tools_test.php`: the row's gate, its list and the screens that mark it.

Related: docs/memory/ui/settings-system-tools.md, docs/memory/ui/main-menu-panels.md
