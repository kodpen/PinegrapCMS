---
name: automation-flows-menu-proposals
description: Open questions for the architect from the automation-flows-menu census (AF0): more boxes needing the Workspace, the API routes and dashboard entries with no step, the refusal's step
---

## Which more boxes and starts need the Workspace? (open)
- The plan's list is built (`workspace/automation-flows-workspace-boxes.md`). The census found more that act in the Workspace and still run with it off. Proposed: put them on `ws_flow_kinds_workspace()` (one line each, plus the settings rule of `ws_flow_node_needs_workspace()`).
- Let people know `notify`: a Workspace inbox line with its bell and phone (`ws_push_pending()` asks `ws_enabled()`), unseen with it off.
- The table boxes `row_add`, `row_set`, `row_get`, `rows_get` and the start When a table row changes `row`: workflow tables open only on `workspace_wtables.php` (`ws_screen_gate()`), so rows written are never seen and the start never comes.
- Ask Pinegrap AI `ai`: its settings are in the Workspace's settings, and the designer (`pg_design_ai_workspace()`) and the translation engine refuse Pinegrap AI with the Workspace off.
- Run a tool of an MCP server `mcp`: servers and accounts are connected on Workspace screens; a tool that writes asks the owner in a channel.
- Change a record and Read a record of a Workspace record (`channel`, `task`, `task_note`, `note`); a start on the Workspace's own events (`ws_events_own()` but `workspace.flow.run.failed`, which flows cause); Start a scheduled action `start_action` (never offered; a scheduled action that is no flow waits for the Workspace).

## Who builds the API's part? (open)
- No step names the 26 flow routes and the run events (census). Proposed: a step after AF1, beside AF2: with the Workspace off `api_modules()` merges the flow routes, their schemas and the four `workspace.flow.run.*` events alone, ids, paths and scopes unchanged, so a key keeps working when the switch changes.

## Do the dashboard's flow entries follow the screen? (open)
- The Flows card, the "Flow runs (today)" figure and the Start flow shortcut use the dashboard gate `flows` (role < 3 and the `workspace` gate). Proposed: AF4 moves them onto the screen's gate with the menu row (`ui/main-menu-entry-gates.md`).

## Where did the run-time refusal land?
- In AF1: once flows run with the Workspace off, a box on the list must not run, so `ws_flow_step_run()` refuses it there (the plan's rule). AF3 keeps the palette, Pinegrap AI's catalogue, templates, the problem line and Try on such a box. If the architect wants the refusal in AF3, it is one call to move.

## What did AF1 leave as the Workspace's?
- With it off the scheduled run skips converting scheduled actions, watching channels, approval deadlines and read receipts (`workspace/automation-flows-gate.md`). Hence the approval box is on the list: a request asked with the Workspace off would never expire.
