---
name: workspace-scheduled-actions-api
description: Scheduled actions in the external API (api_scheduled.php): list, get, pause, cancel; staff only; no messages or flow anchors; sentences withheld for unread channels
---

## Which routes are there?
- `GET /workspace/scheduled-actions` (filters `status`, `channel_id`; at most 300, the panel's order), `GET /workspace/scheduled-actions/{id}` (with `history`, the latest 20 runs), `POST …/{id}/pause`, `POST …/{id}/cancel`. All four are MCP tools; key, OAuth and team devices sessions alike.
- Declared in `ws_scheduled_api_routes()` (includes/workspace/api_scheduled.php), merged at the end of `ws_api_routes()`; object `WorkspaceScheduledAction`.
- Resume, create and change are not in the API yet (plan S2, open question 1: who may create).

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
