---
name: workspace-scheduled-actions-api
description: Scheduled actions in the external API (api_scheduled.php): list, get, pause, cancel; create, change, resume from the person's own session; no messages or flow anchors; sentences withheld
---

## Which routes are there?
- `GET /workspace/scheduled-actions` (filters `status`, `channel_id`; at most 300, the panel's order), `GET /workspace/scheduled-actions/{id}` (with `history`, the latest 20 runs), `POST …/{id}/pause`, `POST …/{id}/cancel`. All four are MCP tools; key, OAuth and team devices sessions alike.
- Declared in `ws_scheduled_api_routes()` (includes/workspace/api_scheduled.php), merged at the end of `ws_api_routes()`; object `WorkspaceScheduledAction`.
- `POST …/{id}/resume`, `POST /workspace/scheduled-actions` (201) and `POST`/`PATCH …/{id}`: staff from their own team devices session only (`ws_api_own_words_only()`), `'mcp' => false`: an action runs in the name of whoever saves it (owner, #1708, `workflow/decisions-made-product.md`). Added in #1858. A connected app proposes one instead, as a draft card (`workspace-change-proposals.md`).

## Who may call them?
- Staff in the team only (`ws_can_schedule()` in `ws_api_scheduled_viewer()`), 403 for anybody else; 503 before the tables exist. Every staff member sees every action, as on the panel's screen.

## What is never reached?
- A person's scheduled message (`ws_scheduled_action()` and `ws_scheduled_actions_only()`) and the hidden row that starts a flow (`flow_id` > 0: `ws_api_scheduled_is_flow_anchor()`, and `flow_id = 0` in the listing when the column exists): 404 / left out.

## What is withheld?
- An action names channels: its home, the `channel_id` of its rules (join, event) and of its action and follow-ups (-1, the joined channel, is none). Unless the caller reads all of them (`ws_can_read_channel()`, and `ws_claude_channel_allowed()` for Claude's key), `texts_withheld` is true and `when`, `conditions`, `action_text`, the follow-ups' `text` and run `summary` are null, since those sentences carry channel names and message excerpts. `channel_name` and `message_id` are given only when the home channel is read; `channel_id` always.
- The note an action belongs to is not given: notes are their owner's own.

## How do pause and cancel write?
- `ws_scheduled_set_status_check()` (scheduled.php, shared with the panel's `ws_scheduled_status`) → `api_dry_run_stop()` → `ws_scheduled_set_status()`. Done or cancelled: 422. Pausing a paused one is accepted and changes nothing. Cancel drops the starts waiting in the queue.
- The four route ids are lines of `workspace_flows_parity_table()` (group `api_routes`, slice 5).

## How do create and change write?
- `ws_scheduled_save_check()` (scheduled.php; refusals 403 / 404 / 422, also run by the panel's `ws_scheduled_save()`) → a rehearsal answers `ws_scheduled_preview()`'s words → `ws_scheduled_save()` inside `ws_api_as_owner()`.
- Body: `name`, `rules` (list), `action` (the `object` type of the router and the OpenAPI document: a JSON object, a list refused), `follow` (list of {on, do}), `channel_id` on create only. A change keeps every field it leaves out (`ws_api_scheduled_change_data()`, pure); kept rules go to the save as the form sends them, a money condition back in major units (`ws_api_scheduled_rules_as_sent()`), or each rename multiplied its threshold by 100; a resume goes through `ws_scheduled_set_status_check()`.
- Tests: `tests/workspace_scheduled_api_test.php`; the three ids are lines of the parity table's `api_routes` group.
