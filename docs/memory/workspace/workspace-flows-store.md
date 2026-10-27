---
name: workspace-flows-store
description: Workspace flows store (graph check, API; runs: workspace-flows-runner.md): the four tables (11.0.80), graph JSON, problem codes, access, routes, why publishing is the person's own
---

## What is there?
- Tables (2026.11.0, 11.0.80; probe `ws_flows_ready()`): `ws_flows` (owner, home `channel_id`, name, status draft/live/paused/binned, `draft` JSON, `draft_rev`, live `version_id`), `ws_flow_versions` (flow_id, n, graph; never changed, UNIQUE (flow_id, n)), `ws_flow_runs`, `ws_flow_run_steps` (runner), `ws_scheduled_actions.flow_id` (hidden trigger action later). VARCHAR statuses, no JSON in SQL.
- Code: `includes/workspace/flows.php`, handlers `api_flows.php`, routes `ws_api_routes()`; tests `tests/workspace_flows_test.php`. Screen, panel actions: `workspace-flows-canvas.md`.

## What does a graph look like?
- `{"v":1, "nodes":[{id, kind, label, x, y, config}], "edges":[{from, to, on}], "notes":[{id, text, x, y, w, h}]}`. Ids `^[a-z][a-z0-9_]{0,31}$`.
- Kinds (`ws_flow_kinds()`): `manual` (outcome next; config subject), `task` (done, cancelled; title, description, assignees or an assign rule of `ws_template_assign_valid()`, due_in, priority), `end`.
- `ws_flow_graph_normalize()` (pure): casts, drops unknown keys, cuts texts by characters, valid UTF-8 everywhere (a byte cut once broke `json_encode()`), a missing `on` is the box's first outcome.
- `ws_flow_graph_check()` (pure) codes: too_many, bad_id, duplicate_id, unknown_kind, bad_config, no_trigger, two_triggers, bad_edge, arrow_into_trigger, bad_outcome, duplicate_edge, cycle (Kahn, boxes on the loop only), unreachable. Notes are not checked. `ws_flow_problems()` adds the subject against `ws_scheduled_record_types()`.
- bad_id, bad_config beyond the plan: an id becomes `run_steps.node_id`; a task box needs a title.

## Who may do what?
- `ws_flow_access()`: edit = owner on the staff (`ws_can_schedule()`), or a staff colleague shared at edit; run = reader of a live flow's home (public/private), or a run share; view = view or channel share. Binned, admins by rank (as `ws_note_access()`), site members: ''. Publish, share, delete, home move: owner only (`workspace-flows-shares.md`).
- Limits: 100 boxes, 200 notes (problems: a draft may hold them), 256 KB JSON, 200 flows per owner (refused).

## How do the writes work?
- `ws_flow_save_check()` / `ws_flow_save()`: name 1-160; home public/private and readable (checked on a change only; not in the draft: changes at once, runs keep theirs); graph normalized; problems answered, not refused. UPDATE `WHERE draft_rev = ?` raises it; 0 rows → 409 `changed_meanwhile`.
- `ws_flow_publish_check()` / `ws_flow_publish()`: no problem, task boxes with people pass `ws_task_create_check()`; n = MAX+1 via `@mysqli_query` (db() ends the request on 1062), duplicate → 409; status live (also from paused; resume: `workspace-flows-versions.md`).
- `ws_flow_pause*()` live → paused (open runs go on); `ws_flow_bin*()` binned, open runs cancelled. Create, publish, pause, delete write a Site Log line (API inside `ws_api_as_owner()`); draft saves do not.

## Which routes?
- GET/POST `/workspace/flows`, GET and POST (PATCH) `/{id}` (`draft_rev` required), POST `/{id}/publish`, `/{id}/pause`, DELETE `/{id}` and POST `/{id}/delete`. Body parts `nodes`, `edges`, `notes` (one not sent is kept). All writes `dry_run`; a draft rehearsal answers its problems.
- Objects WorkspaceFlow, WorkspaceFlowVersion, WorkspaceFlowGraph, WorkspaceFlowProblem. Draft and problems to editors on a single read (readers: the draft of a flow never published).

## Why is publish own-words only? (owner, 2026-10-24)
- Decisions: own vanilla editor, staff build, publishing decides, AI drafts, a person publishes. A published flow opens tasks in the owner's name, like a scheduled message, so publish has `ws_api_own_words_only()` and `'mcp' => false`; key, OAuth and MCP may list, read, draft, pause and delete. Moving a published flow's home (who may start it) is refused to key and OAuth (`ws_api_flow_home_move_gate()`); it stays a tool.
