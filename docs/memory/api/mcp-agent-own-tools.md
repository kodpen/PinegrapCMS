---
name: mcp-agent-own-tools
description: An application agent's own routes as MCP tools ('mcp' => 'agent', #10867): which ten, only for an active app agent's own key, the callers that never see them, the budget, the second sending form
---

## Which routes are tools for the agent?
- Ten rows carry `'mcp' => 'agent'`: `workspace.agent_instructions.get`, `workspace.agent_requests.list` / `claim` / `brief` / `answer` / `fail` / `progress`, `workspace.agent_report.set`, `workspace.agent_wake.get` / `set`. Tool name = route id with dots as underscores (`workspace_agent_requests_claim`).
- `workspace.agent_feedback.get` stays `'mcp' => false`: not in the plan's list (U16).
- Why: a Claude Console agent (Claude Managed Agents) reaches the site over MCP with one bearer token from a vault (`claude-console-agents.md`, `api-app-token.md`); before #10867 it could not work its queue there.

## Who sees them?
- Only a key used as itself whose application has an active `app` agent: `pg_mcp_agent_routes_open($device, $agent)` (pure) with `api_current_device()` and `ws_api_agent_caller_row()`, read once a request by `pg_mcp_caller_is_agent()`; `pg_mcp_routes()` passes it to `pg_mcp_routes_for($schema, $scopes, $agent)`.
- Never: an OAuth connection or a team devices session (device rows: they act as their person, who must not answer or report as the agent), a key with no active agent (none, paused, or a model agent). The key's scopes still decide each tool. Same on the compact address (`tools_find`, `tools_describe`).
- `pg_mcp_route_is_offered($route, $agent = false)`: an `'agent'` row is offered only when `$agent === true`; `'mcp' => false` stays off for everyone. The handlers still call `ws_api_agent_caller()` (403 otherwise).

## Do they fit the tool budget?
- `tests/mcp_tool_budget_test.php` measures them (it builds the list with `$agent` true). Answer, progress, brief, report and wake set moved lookup text to `details` (OpenAPI only); the shared closing sentence is "For an active application agent's own key only (Workspace Settings, Agents): any other caller 403 forbidden."

## What do the agent's texts say?
- Instructions: `-u` first, then the second form for an environment that puts a secret in only when sent word for word: `X-Pinegrap-Key` / `X-Pinegrap-Secret` headers, or `Authorization: Bearer $PINEGRAP_TOKEN` (`api-app-token.md`); then the MCP address (`ws_agent_instructions_mcp()`) and the tool naming. The version changed (`agent-instructions.md`).
- Start text: the same two forms after `-u` (`workspace/agent-start-text.md`). The brief's tool line names the own routes as tools for the own key and the feedback read as HTTP only (`workspace/agent-route-words.md`).

Tests: tests/mcp_agent_tools_test.php, tests/workspace_agent_instructions_test.php, tests/workspace_agent_start_text_test.php.
