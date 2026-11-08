---
name: workspace-note-groups
description: Workspace note groups and the notes lock — tables of step 11.0.119, readiness probes ws_note_groups_ready() / ws_note_lock_ready()
---

## Which tables hold note groups and the lock?
- Step `upgrade_2026_11_0_workspace_note_groups()` (2026.11.0, label 11.0.119): `ws_note_groups` (a person's folders: name, `ws_palette()` colour place, sort, locked), `ws_note_places` (user_id, note_id → group_id, sort; one group per person and note), `ws_note_group_shares` (shaped as `ws_note_shares`), `ws_note_locks` (PIN hash, open_minutes 0 = every time / 65535 = till sign-out, fails, wait_until), `ws_notes.locked`, `ws_note_shares.via_group` (+ `idx_via_group`). Shapes approved in `workflow/decisions-made-schema-12.md`; all four tables are in `get_tables()`.

## How does code know the schema is there?
- `ws_note_groups_ready()` (groups, places, group shares, `via_group`) and `ws_note_lock_ready()` (locks table, both `locked` columns) in `includes/workspace/notes.php`, through `pg_schema_has()`. Every reader and writer of groups or the lock asks them first; until the step ran, notes work as before.

## Why are a group's shares written as note shares?
- A group's share writes `ws_note_shares` rows with `via_group` = the group, so every reader of note shares (access, files, list, sync, deletions) stays as it is, and taking the group back deletes only its rows; a direct share (`via_group` 0) stays.

## What does the groups store do?
- `includes/workspace/note_groups.php`: `ws_note_groups()` (own groups in order with note counts), `ws_note_group_save()` / `_check()` (make last in order, rename, recolour with a `ws_palette_place()`; names via `ws_note_group_name()`, at most `WS_NOTE_GROUPS_MAX` 200 groups of `WS_NOTE_GROUP_NAME_MAX` 100 characters), `ws_note_groups_order()`, `ws_note_group_delete()` (its notes go back to no group, none deleted), `ws_note_place()` / `_check()` (a note one owns or has shared with them by name, into an own group at a position, or out of every group).
- Order: `ws_note_group_order_insert()` and `ws_note_group_order_merge()` are pure (tests in `tests/workspace_note_groups_test.php`); an asked order missing some ids keeps the rest after it, so a stale screen loses nothing.
- `ws_notes_list()` answers `groups` and each note's `group_id` / `group_sort`; `ws_note_get` carries the same two fields.
- Cleanup: `ws_note_delete()` drops every place of the note (`ws_note_places_forget_note()`); `ws_note_unshare()` drops the reader's place once no share by name is left (`ws_note_places_forget_reader()`).
- Panel actions: `ws_note_groups`, `ws_note_group_save`, `ws_note_group_order`, `ws_note_group_delete`, `ws_note_place`; each answers the groups again.
- Shared groups (G5): `workspace-note-group-shares.md`; `ws_note_place()` asks its gate and syncs the groups a note leaves and enters.
- The API and MCP: `workspace-note-groups-api.md`.
- Not yet: the lock hiding a locked group's count (L steps).
