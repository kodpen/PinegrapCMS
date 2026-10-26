---
name: workspace-mcp-servers-api
description: The site's MCP servers in the Workspace API (api_mcp.php, PR #1800): list with state, add, probe, refresh, tool rules; administrators only, no MCP tool; checks and writes shared with the settings card
---

## Which routes are there?
- Administrators only (`ws_api_mcp_admin()`: 403 for an assistant's key, which an administrator makes, and below role 0; 503 before the schema), all `'mcp' => false`: a request written in a channel cannot have Claude add a server or turn its tools on.
- GET /workspace/mcp-servers: each server with `state`, `reason`, `checklist` (`ws_mcp_server_state()`, the card's badge too), its tools with `hint` (reads_only, not_said, writes, destroys) and `rule`. No credential is ever given back.
- POST /workspace/mcp-servers: add (alias from the host when left out, `ws_mcp_alias_free()`); the typed token, header value, password or client secret is sealed with `ws_mcp_secret_encode()`. No OAuth: the tools are read at once (`read` in the answer). OAuth: `ws_mcp_oauth_setup()` only; the sign-in stays the settings card's, since a person signs in in a browser. 409 for an address already added or a full site.
- POST /workspace/mcp-servers/probe, /{id}/refresh (200 with `read.ok` false when the server cannot be reached; 409 when it cannot be asked), /{id}/tools (rule off, on or approve for each named tool, the others kept; `declare_all` is the card's warned button).
- Not offered: change, turn off, remove, connect.

## Which code do the card and the API share?
- `ws_mcp_save_check()` / `ws_mcp_save()`, `ws_mcp_tools_check()` / `ws_mcp_tools_save()`, `ws_mcp_refresh_check()` (also the first step of `ws_mcp_refresh_tools()`), `ws_mcp_probe_check()` / `ws_mcp_probe_address()`, `ws_mcp_server_state()`.
- The form sends every tool (one missing is off); the API passes `ws_mcp_tool_rules()` as the start, so it changes only the named ones. `unknown` and `refused` names are 422 in the API; the form drops them, as before.
- Every write has dry_run, stopped after its check; a rehearsal asks no server (no probe, no tool read, no OAuth registration). The writes log in the session's name, so the API calls them inside `ws_api_as_owner()`.

## How is it checked?
- tests/workspace_mcp_test.php: the tools check for the form and the API, the state, the address check. tests/workspace_api_test.php: routes, administrators, no tool, rehearsal order.
- Never run against a live server through the API; the shared code is what the card ran on 2026-10-24.
