---
name: settings-workspace-gate
description: The Workspace heading of the Site Settings dialog and its gate: who opens which category, where the gate is asked, the Defaults card moved, the link cards to the old screen
---

## Which categories sit under the Workspace heading?
- `workspace_team` (Team and working hours), `workspace_assistants` (Assistants and agents), `workspace_channels` (Channels, mail and voice): heading `workspace`, the fifth, after Running the site. Plan `plans/settings-workspace-move.md`; owner's choice in `workflow/decisions-made-workspace-13.md`.
- Each has `includes/settings/<key>.php`, `<key>.save.php`, `settings_<key>.php`, a `case` in `output_menu()` lighting the Workspace row (23, as `workspace_settings.php`) and a help row `workspace#workspace-settings`.

## Who opens them?
- A registry entry with `'gate' => 'workspace'` opens for a holder of the Workspace settings right alone (`pg_settings_workspace_settings_allowed()`: module on and installed, `ws_rights()['settings']`, which roles 0-2 get as team members). Every other category opens for role 0-2 alone.
- So a role 3 holder of `manage_workspace_settings` gets the gear and the dialog with the Workspace heading only; a manager taken out of the team ("Not in the team") sees no Workspace category and is refused one asked by key; a manager in the team sees all 18.
- One pure rule, `pg_settings_category_open($category, $reader)`; `pg_settings_reader($user)` gives `manager` and `workspace`; `pg_settings_categories_for($user)` and `pg_settings_may_open($key, $user)` build on it (`includes/settings/registry.php`).

## Where is the gate asked?
- `settings_pane.php`: nobody past the door without one category (others get `validate_area_access($user, 'manager')`'s refusal as before); a pane the reader may not open answers 403 JSON before it is drawn or saved; the search index reads the reader's categories.
- `includes/settings/screen.php` (the page path) before it draws or saves; its category switcher and Previous/Next list the reader's categories.
- `pg_settings_fragment()` and `pg_settings_apply()` ask again, so no caller draws or writes a category for a reader who may not open it.
- `includes/settings/modal.php` (gear and list, System tools row for managers only), the dialog's search (tool rows for managers only) and the command palette (`includes/panel/search.php`: the reader's categories; plain "Settings" and the tools for managers only).
- A Workspace category runs no `includes/settings/prep.php`: its cards read nothing of it, and a basic user never runs the managers' preparation.
- Refusals: a Workspace category logs "access denied to the workspace settings", any other the manager refusal of `validate_area_access()`.

## What moved, and what is left on the old screen?
- Defaults (`pgset-ws-defaults`) is in Team and working hours, fields `ws_day_hours`, `ws_workdays[]` (a `lists` entry), `ws_default_task_hours`, saved with the pane's Save; a request without the two hour fields leaves the defaults alone. The checks and the write are `ws_settings_defaults_save()` (`includes/workspace/workdays.php`), which the old screen's `defaults` case calls too; a refused hour is marked on its field.
- `ws_settings_mask()`, `ws_settings_days_html()` and `ws_settings_hours()` moved there from `workspace_settings.php`, so both screens draw them.
- `workspace_settings.php` draws a line linking the card's new place where Defaults was.
- Each Workspace category ends with an "On the Workspace Settings screen" card (`pgset-ws-*-screen`) linking the cards not moved yet (`ws_settings_screen_links_card()`, `includes/workspace/screen.php`); a step that moves a card removes its link, SW7 the card.

Tests: `tests/settings_workspace_gate_test.php`.
