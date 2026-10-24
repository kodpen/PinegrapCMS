---
name: workspace-flows-store
description: Workspace flows, first slice (store, graph check, API; runs in workspace-flows-runner.md): the four tables (11.0.80), graph JSON, problem codes, access, routes, why publishing is the person's own
---

## What is there?
- Tables (2026.11.0, 11.0.80; probe `ws_flows_ready()`): `ws_flows` (owner, home `channel_id`, name, status draft/live/paused/binned, `draft` JSON, `draft_rev`, live `version_id`), `ws_flow_versions` (flow_id, n, graph; never changed, UNIQUE (flow_id, n)), `ws_flow_runs`, `ws_flow_run_steps` (the runner: `workspace-flows-runner.md`), `ws_scheduled_actions.flow_id` (the hidden trigger action later). VARCHAR statuses, no JSON in SQL.
- Code: `includes/workspace/flows.php` (store), `api_flows.php` (handlers), routes in `ws_api_routes()`. Tests: `tests/workspace_flows_test.php`.

## What does a graph look like?
- `{"v":1, "nodes":[{id, kind, label, x, y, config}], "edges":[{from, to, on}], "notes":[{id, text, x, y, w, h}]}`. Ids `^[a-z][a-z0-9_]{0,31}$`.
- Kinds (`ws_flow_kinds()`): `manual` (outcome next; config subject), `task` (done, cancelled; title, description, assignees or assign rule of `ws_template_assign_valid()`, due_in, priority), `end`.
- `ws_flow_graph_normalize()` (pure) casts, drops unknown keys, cuts texts by characters, makes every string valid UTF-8 (a byte cut once made `json_encode()` fail), fills a missing `on` with the box's first outcome.
- `ws_flow_graph_check()` (pure) codes: too_many, bad_id, duplicate_id, unknown_kind, bad_config, no_trigger, two_triggers, bad_edge, arrow_into_trigger, bad_outcome, duplicate_edge, cycle (Kahn, only boxes on the loop), unreachable. Notes never checked. `ws_flow_problems()` adds the subject against `ws_scheduled_record_types()`.
- bad_id and bad_config were added to the plan's list: an id becomes `run_steps.node_id`, a task box needs a title.

## Who may do what?
- `ws_flow_access()`: edit = owner with `ws_can_schedule()` (staff), not binned; run = live flow whose home channel (public/private) the viewer reads; else ''. No admin view (as `ws_note_access()`). Site members never.
- Limits: 100 boxes, 200 notes (problems, so a draft may hold them), 256 KB JSON and 200 flows per owner (refused).

## How do the writes work?
- `ws_flow_save_check()` / `ws_flow_save()`: name 1-160, home channel public/private and readable (checked only when it changes; not in the draft: it changes at once, runs keep theirs), graph normalized; problems are answered, not refused. UPDATE `WHERE draft_rev = ?` raises it; 0 rows → 409 `changed_meanwhile`.
- `ws_flow_publish_check()` / `ws_flow_publish()`: no problem, task boxes with people pass `ws_task_create_check()`; version n = MAX+1 through `@mysqli_query` (db() would end the request on 1062), duplicate → 409; status live. Publishing a paused flow makes it live again (no resume route yet).
- `ws_flow_pause*()` live → paused (runs going go on); `ws_flow_bin*()` status binned, its open runs cancelled. Create, publish, pause and delete write a Site Log line (API inside `ws_api_as_owner()`); draft saves do not.

## Which routes?
- GET/POST `/workspace/flows`, GET and POST (PATCH) `/workspace/flows/{id}` with `draft_rev` required, POST `/{id}/publish`, `/{id}/pause`, DELETE `/{id}` and POST `/{id}/delete`. Body parts `nodes`, `edges`, `notes` (a part not sent is kept). All writes `dry_run`; a draft rehearsal answers its problems.
- Objects WorkspaceFlow, WorkspaceFlowVersion, WorkspaceFlowGraph, WorkspaceFlowProblem. Draft and problems only to the owner on a single read.

## Why is publish own-words only? (owner, 2026-10-24)
- Decisions: own vanilla editor, staff build, publishing decides, AI drafts only and a person publishes. A published flow later opens tasks in the owner's name, like a scheduled message, so publish has `ws_api_own_words_only()` and `'mcp' => false`; key, OAuth and MCP may list, read, draft, pause and delete. Moving a published flow's home decides at once who may start it: refused to key and OAuth (`ws_api_flow_home_move_gate()`); the change stays a tool.
