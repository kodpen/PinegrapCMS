---
name: automation-flows-workspace-boxes
description: Which flow boxes and starts need the Workspace (ws_flow_kinds_workspace()): the list, offered only with it, the problem on the canvas, the refusal at run time and in Try
---

## Where is the list?
- `ws_flow_kinds_workspace()` beside `ws_flow_kinds()` (`includes/workspace/flows.php`), with the pure `ws_flow_node_needs_workspace($node)` for a box whose settings decide. One place: the runner's refusal, Try, the palette, the problem line, templates, the quick setup and Pinegrap AI's catalogue read it.
- The rule (`automation-flows-decisions.md`, after the owner's M1a, #8961): a box or start that needs the Workspace is offered only while it is on; on a site without it a flow holding one shows a problem and that box does not run.

## Which kinds are on it?
- Starts `join`, `mention`, and When a table row changes `row`.
- A message in a channel: `post`, `checkin`, `approval` (its request is a channel message, and approval deadlines are not run with the Workspace off). Counting approval as a channel message is the implementing session's reading.
- `lead`; the task boxes `task`, `task_open`, `task_digest`; `agent`.
- The table boxes `row_add`, `row_set`, `row_get`, `rows_get` (tables open only on a Workspace screen); Ask Pinegrap AI `ai` (its settings are the Workspace's); `mcp`; `start_action` (#9225).

## Which boxes do their settings decide?
- `report` when it posts into a channel (`channel_id` > 0); one sent only by e-mail runs.
- Change a record `change` and Read a record `record_get` on a Workspace record (`ws_flow_records_workspace()`: `channel`, `task`, `task_note`, `note`).
- An event start `event` on one of the Workspace's own events (`ws_flow_events_workspace()`: `ws_events_own()` but `workspace.flow.run.failed`, which flows cause with it off too).

## What runs without the Workspace?
- `manual`, `schedule`, a site event or a failed run, `no_order`, `web`; Send an e-mail, Call a webhook, Check a web address, Start a flow, Change or Read a site record, Condition, Wait, For each, End, Constants, Derived value; Let people know `notify` (off the list since #9288: its lines reach the bell, `automation-flows-bell.md`).
- A run still writes its owner's flow_finished line in the Workspace inbox; with the Workspace off the bell shows it (`automation-flows-bell.md`).

## Where is such a box not offered with the Workspace off?
- The canvas palette and adding a box (`memberKind()` reads `FL.workspace_kinds`), the templates (`ws_flow_templates_for()`, `ws_flow_graph_needs_workspace()`), the quick setup (`ws_flow_quick_fill()`), Pinegrap AI's catalogue (`ws_flow_ai_catalogue()`). With it on, all as before.
- The editor's choices: `ws_flow_steps_workspace_choices(ws_enabled(), $steps)` (pure) takes the Workspace records out of the Change and Read a record pickers and its own events out of the event list.
- The problem line: `ws_flow_workspace_problems(ws_enabled(), $graph)` (pure, code `workspace_off`, "The box …: It needs the Workspace, which is switched off on this site.") joins the graph's problems in the save answer, the opened flow and the publish check: it stops Publish, so a flow kept from Workspace days is published again only without it. A live one keeps running; that box fails.
- Try refuses it with the run's words (`ws_flow_box_try()`, after the member's refusal).

## What happens at run time?
- `ws_flow_step_run()` asks `ws_flow_workspace_box_refusal(ws_enabled(), $node)` after the role-3 member's refusal: the box fails with "This box needs the Workspace, which is switched off on this site, so it did not run.", without its retries. With the Workspace on nothing changes.
- Pinned by `tests/automation_flows_gate_test.php` (listed kinds are real; the site-only kinds are off it; the record and event rules; the editor's choices).
