---
name: bug-hunt-areas-agent-guide
description: The bug hunt in the AI agent guide and the refused calls (built in #10709 to #11236), PR #11805: the faults fixed, what was read clean and what was seen and left
---

## Which area was it?
- The guide over its three doors (`GET /guide`, MCP `guide_read` and `pinegrap://guide`, the panel's `agent_guide.php` and `llms.txt`), the engine's limits, the compact tool list, hints on refusals, the Refused calls card and `GET /reports/api-refusals`. Named by the architect on 2026-11-26 (agent channel request 3068), hunted in #11805. Overview: `api/agent-guide-overview.md`. The designer topic's content was not in it.

## What was fixed?
- The panel topic printed its Visual Page Editor section, with `design_pages_get` and `design_proposals_create`, to a manager, a role 3 user and a caller with no viewer, while the same topic says a screen not listed answers Access denied. It now comes only with the `design` gate (`api/agent-guide-panel.md`).
- The refused calls report never folded a tool called over MCP to its route: such a call is logged as `/mcp` plus the route's path, so every MCP refusal had `route` null and its ids left in by type. It now folds, path `/mcp/<template>` (`api/api-refusals-report.md`).
- The Refused calls card linked a guide section to `agent_guide.php?topic=…` for a topic the viewer cannot read there (a manager and the designer topic): the link answered 404. Only a topic the door lists for the viewer is linked now.

## What was read and found clean?
- Who reads what: `GET /guide` lists topics by the caller's effective scopes, the panel door by `api_owner_scopes()` of the signed-in user, a module topic only while the module is on; no door answers without a key, a connection or a panel session; `llms.txt` in the software folder goes through `validate_user()`.
- `{tool:…}` marks: the tests fail on a route that is gone; module lines of start are guarded; tool names are ASCII and cut at 64 bytes.
- Compact list: `pg_mcp_routes()` is filtered by scope before `tools_find`, `tools_describe`, `tools_read` and `tools_write`, so a tool hidden by scope is hidden in both lists; every money route is a write and refused through `tools_write`; `_post` twins are never MCP tools.
- Hints: static English text naming no record, id or name; their guide sections point at start or, for the designer's codes, at a topic only design routes answer with.
- Report: the log keeps the path without the query string, no key, token or body; the card escapes path, code and application name; the window is the last `days` × 86,400 seconds.

## What was seen and left?
- Asked (architect): the start topic is listed to every caller and its lines name tools the caller may not hold, such as `design_pages_get` and `launch_read` (roles 0 and 1 only) for a manager's key or a Content connection. A line rule (keep a line only when the reader holds a tool it names) would change what every agent reads, so it was not built.
- The opening instructions hold 3,072 bytes only for a short site name: the test site (`example.com`) gives 3,018 bytes on the compact address, so an organisation name and host of about 55 bytes more go past the limit. Nothing breaks at run time; the limit is a test budget.
- The opening instructions name products, orders, channels, the store and the Workspace whatever modules are on.

Related: docs/memory/api/agent-guide-overview.md, docs/memory/workflow/bug-hunt.md
