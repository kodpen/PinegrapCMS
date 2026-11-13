---
name: automation-flows-menu-steps-2
description: Steps AF3b, AF3c, AF4b, AF4c of the automation-flows-menu plan, decided after the census (AF0): more boxes that need the Workspace, the API routes, the dashboard entries, the bell
---

## What is decided after AF0? (architect, 2026-11-13)
- The three proposals of the census (`automation-flows-menu-census.md`) are taken, each as a step of its own. The run-time refusal stays where AF1 put it (`ws_flow_step_run()`).
- No running site changes: before AF1 no flow ran where the Workspace is off.

## Which steps?
- AF3b (S) Done #9225. More kinds on `ws_flow_kinds_workspace()`: the table boxes `row_add`, `row_set`, `row_get`, `rows_get` and the start `row` (workflow tables open only on a Workspace screen); Ask Pinegrap AI `ai` (its settings are the Workspace's; the designer and the translation engine refuse it the same way); `mcp`; `start_action`; Let people know `notify` until AF4c lands.
- AF3b, by settings (`ws_flow_node_needs_workspace()`): Change a record and Read a record on a Workspace record (`channel`, `task`, `task_note`, `note`); an event start on one of the Workspace's own events (`ws_events_own()`), but `workspace.flow.run.failed`, which flows cause. The test's two lists follow (`workspace/automation-flows-workspace-boxes.md`).
- AF3c (S) Done #9257. The API: with the Workspace off `api_modules()` merges the 26 flow routes, their schemas and the four `workspace.flow.run.*` events alone; ids, paths and scopes unchanged, so a key keeps working when the switch changes. The site's MCP server offers the same tools; `check_api_schema.php` stays green.
- AF4b (S) The dashboard: the Flows card, the "Flow runs (today)" figure and the Start flow shortcut ask what the Flows screen asks (`pg_flows_menu_row()`), not the dashboard gate `flows` with the Workspace's; the card's title reads Automation flows (`ui/main-menu-entry-gates.md`).
- AF4c (S) Done #9288 (`workspace/automation-flows-bell.md`). The bell: with the Workspace off the panel's bell shows the Workspace inbox lines flows write (a run's finished and failed lines for its owner, Let people know's lines); what `pg_panel_get_notifications()` lacks for it is built. `notify` then leaves the list. MA10c of `messages-area-steps-2.md` takes the same way for the mail notices.

## In which order?
- The four in any order, each alone; AF6 (close-out) after them. Claim as `[plan:automation-flows-menu] <step> — <topic>`.

## Why these answers?
- A box that acts where nobody can look is not offered: rows nobody opens, a notice nobody sees. Let people know is the one a shop without the Workspace needs (an order came), so it comes back with the bell instead of staying off.
- The API and the dashboard follow the owner's M1a (flows are on at every site, behind the right only): a key or a card that goes dark with the Workspace's switch would be a second switch.
