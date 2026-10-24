---
name: api-mcp-server
description: The site as an MCP server (mcp.php, includes/mcp/, 2026.4.10): tools are the API's routes, which routes are left out, how tools/call runs a handler, addresses, callers, protocol generations
---

## Where do the tools come from?
- Every route is a tool (`pg_mcp_tool_from_route()`, `includes/mcp/tools.php`): a new endpoint brings its tool with no line here. Name = the route id with dots as underscores; description = `summary` + `description`; input = the declared parameters, plus `dry_run` on a rehearsable write. These texts are what the model reads: English, short and true. Not `destructiveHint`: a GET, ids ending `create` or `propose`.
- An AI app changes a record by proposing it: `workspace_changes_propose` and `workspace_tasks_changes_propose` leave cards the person applies (`workspace/workspace-change-proposals.md`).
- Left out (`pg_mcp_route_is_offered()`): routes with no scope or `'public' => true`, ids starting `auth.`, `push.` or `docs.`, ids ending `_post` (the IIS twins), and routes with `'mcp' => false` (below). An IIS POST twin's id must end in `_post`, or one action becomes two tools. Only routes the caller's scopes cover are listed.

## Which routes carry `'mcp' => false`, and why?
- `GET /workspace/sync` that an app polls, file downloads and uploads, and own-words routes an OAuth client could never call (`workspace/workspace-api-callers.md`).
- Claude's queues: every route whose handler calls `ws_api_claude_guard()` (`workspace.claude.requests.list`/`claim`/`answer`/`fail`, `workspace.claude.change_types`, `workspace.claude.record`) or `translate_api_claude_guard()` (the five `translations.jobs.*`). They answer only the key of Claude's application, never an OAuth connection, and the routine calls them over REST, so as tools they would refuse every client. Keyed on the guard, not on `ws_claude_api_routes()`: `workspace.channels.context` has none and stays a tool (`test_ws_api_claude_only_routes_are_no_tools`).
- The person's decisions on a card: `workspace.approvals.decide`, `workspace.changes.apply`, `workspace.changes.dismiss` (owner decision 2026-10-24: made on the card). Over MCP an assistant would decide in the person's name or apply its own proposal. REST is unchanged: a device session or an OAuth connection may call them, a key gets 403 (`test_ws_api_card_decisions_are_no_tools`).

## How does tools/call run a route?
- In-process, with the same checks as `integration.php`: scope, `api_validate_input()`, then the handler. The handler still leaves through `api_send()`/`api_fail()`; `api_send_hook()` turns the answer into the JSON-RPC result (a refusal becomes a result with `isError`).
- So a handler never reads `$_SERVER`, `$_GET` or `php://input` itself: it uses `api_request_input()` and `api_header()`, which the MCP endpoint fills through their setters (`X-Dry-Run` included), and `$_FILES` only while `api_send_hook() === null`.

## Which address, and who may call?
- Given out at the site root, `site.com/mcp.php` (`router.php` hands it to `pinegrap/mcp.php`); the file in the software folder answers too. Both are listed in `pg_oauth_resource_addresses()['mcp']` and each gets its own protected-resource document, because a client compares the document's `resource` with the address it connected to. Compare resources with `pg_oauth_resource_key()`.
- Callers: an OAuth token issued for `mcp` (one issued for `integration` is refused), a team devices token or an application key, with the API's rate limit and request log. No session (`PG_NO_SESSION`); a call without credentials gets a 401 whose challenge says where to sign in.

## Which protocol?
- Streamable HTTP: one POST carries one JSON-RPC message and gets one complete JSON answer. No session id is handed out and nothing is streamed (no SSE) (`includes/mcp/protocol.php`).
- Two generations: 2026-07-28 (no `initialize`; the version in `MCP-Protocol-Version` or `_meta`, the method and tool name repeated in `Mcp-Method`/`Mcp-Name`, which must match the body) and 2024-11-05 to 2025-11-25, which open with `initialize`.
