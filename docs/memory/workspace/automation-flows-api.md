---
name: automation-flows-api
description: Automation flows in the external API and MCP with the Workspace off (AF3c): the flows module of api_modules(), what it merges, the scopes and their ceiling, how it is tested
module: automation_flows
---

## Do the flow routes answer with the Workspace off?
- Yes, once flows run on the site (`pg_flows_enabled()`, `automation-flows-gate.md`). `api_modules()` lists a `flows` module, on when `api_modules_flows_alone(WORKSPACE_ENABLED, pg_flows_enabled())` (pure): Workspace off and the flow tables there. With the Workspace on its own `includes/workspace/api.php` carries the flows as before.
- Its file `includes/workspace/api_flows_alone.php` (prefix `ws_flows_alone_`) loads `includes/workspace/api.php` and picks from its lists; nothing is copied.

## What does it merge?
- The 26 routes whose id starts with `workspace.flows.` or `workspace.flow_` (flows, versions, templates, kinds, shares, runs): 11 under `workspace:read`, 15 under `workspace:write`. Ids, paths, scopes and handlers are the Workspace's, so a key keeps working when the switch changes.
- The objects named `WorkspaceFlow*` and the four events `workspace.flow.run.finished/failed/cancelled/waiting`.
- The MCP server reads `api_schema()`, so it offers the same tools as with the Workspace on.
- A new flow route keeps an id starting `workspace.flows.` or `workspace.flow_`, and a new flow object a name starting `WorkspaceFlow`, or it stays dark with the Workspace off (`tests/automation_flows_api_test.php` fails for a `/workspace/flow…` path left out).

## Which scopes are offered?
- The Application Access screen shows one row, Automation flows, holding `workspace:read` and `workspace:write`. The ceiling is `ws_owner_scopes()` kept to those two: a member of the team (`ws_rights()`, which never asks the switch). Channel commands and Tasks are not offered.

## How is it tested?
- `tests/automation_flows_api_test.php`: the switch, the 26 routes and their scopes, every `/workspace/flow…` route picked, the events, every object a route or object names is merged, the scope row and the ceiling.
