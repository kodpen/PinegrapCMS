---
name: workspace-team-settings
description: Departments and Team members in Site Settings (Team and working hours) and on Workspace Settings: shared checks and writes, field names, old anchors, Who is in the team
---

## What moved, and what stays on the old screen?
- PR #8721 (SW2a): the Departments card (`pgset-ws-departments`, each department in a drawer) and the Team members card (`pgset-ws-people`) are in Team and working hours. Their old cards keep working on `workspace_settings.php` until SW7; `pg_settings_legacy_anchors()` maps `ws-departments`, `ws-people`, `ws-defaults` to the new cards.
- "Who is in the team" was an info card on the old screen; its text is the Team members card's footnote, and the choice is that table's "Not in the team" column.
- The Working calendar followed (SW2b): [[workspace-holiday-settings]]. With it the category's card linking the old screen (`pgset-ws-team-screen`) went: nothing of the team part is left to link.

## Where are the checks and writes?
- `includes/workspace/team_settings.php` (PR #8721): `ws_settings_candidates()`, `ws_settings_people_check()` (pure: rank rule, hours, nobody takes themselves out, default stored as 0) and `ws_settings_people_write()`, `ws_settings_department_check()` / `_save()` / `_archive()`, and the markup `ws_settings_department_fields()` and `ws_settings_people_rows_html()`, which take a callback naming each field.
- Both screens call them: `workspace_settings.php` (fields `name`, `members[]`, `people[id][x]`, its own forms) and the Site Settings category (`ws_dept[id][x]`, `ws_people[id][x]`, the pane's save). One set of checks, so the two cannot drift until the old screen goes (SW7).
- The category's save (`workspace_team.save.php`) checks team rows, the pressed department and the defaults before it writes anything; a refusal is marked on its field. An archive refused after the writes is a warning.

Tests: `tests/settings_workspace_team_test.php`, `tests/workspace_settings_input_test.php`.
