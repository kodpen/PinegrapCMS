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
