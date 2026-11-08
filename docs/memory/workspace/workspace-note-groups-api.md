---
name: workspace-note-groups-api
description: Note groups in the external API and MCP (includes/workspace/api_note_groups.php): routes, access, refusals, dry run, group_id on note objects; sharing stays on the screen
---

## Which routes are there?
- `GET /workspace/note-groups` (own groups in order, then groups shared with the owner by name or in a channel the application reads, by name; `scope` all/mine/shared), `GET /workspace/note-groups/{id}` (with `note_ids` in the group's order, only notes the owner reads and `ws_api_note_reachable()` lets through), `POST /workspace/note-groups` (201), `POST /workspace/note-groups/order` (`group_ids`, merged as `ws_note_group_order_merge()`), `POST|PATCH /workspace/note-groups/{id}` (name, color), `DELETE` and `POST …/{id}/delete`, `POST /workspace/notes/{id}/group` (`group_id` 0 for none, `position` 0 first / -1 last). Object `WorkspaceNoteGroup` (`ws_api_note_group_schema()`).
- MCP: every route but the two deletes is a tool, as for notes. Sharing a group has no route: it stays on the notes screen, as sharing a note does.

## Who may do what?
- Read: own groups and groups reached through `ws_note_group_reach_of()` with `ws_api_readable_channel_ids()` (so Claude's application sees a channel-shared group only where Claude may be asked). `access` is owner / edit / view.
- Rename, recolour, delete: only the owner (`ws_note_group_for()`), anybody else 404. Order: own groups only.
- Place: the note through `ws_api_note_or_404()`; a group not reached is 422 `group_id`, checked before `ws_note_place_check()` so its remaining `group_id` refusals are the shared group's gate, answered 403 `forbidden`.

## How do refusals and the dry run work?
- Every write calls the panel's check (`ws_note_group_save_check()`, `ws_note_place_check()`, the owner lookup, the readiness 503), then `api_dry_run_stop()`, then the write; the test keeps the order.
- `ws_note_group_save_check()` gives sentences, not codes: field `name` → 422 `validation_failed`, otherwise the group limit → 409 `limit_reached` (readiness is answered before it).

## What does a note object carry?
- `group_id` (`integer?`): the group the reader keeps the note in (their `ws_note_places` row), null for none. A member of a shared group sees it only for notes they put there themselves. The listing adds it for the whole page with one `ws_note_places_add()`; `ws_api_note_present()` looks it up for a single note.
- Tests: `tests/workspace_api_note_groups_test.php`.
