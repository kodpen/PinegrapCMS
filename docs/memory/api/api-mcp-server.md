---
name: api-mcp-server
description: The site as an MCP server (mcp.php, includes/mcp/, 2026.4.10): tools are the API's routes, which are left out, how a route runs, resources and prompts, addresses, callers, protocol generations
---

## Where do the tools come from?
- Every route is a tool (`pg_mcp_tool_from_route()`, `includes/mcp/tools.php`): a new endpoint brings its tool. Name = the route id with dots as underscores; description = `summary` + `description`; input = the declared parameters, plus `dry_run` on a rehearsable write. These texts are what the model reads: English, short and true. Not `destructiveHint`: a GET, ids ending `create` or `propose`.
- An AI app may propose a record change instead of writing it: `workspace_changes_propose` and `workspace_tasks_changes_propose` leave cards the person applies (`workspace/workspace-change-proposals.md`).
- Left out (`pg_mcp_route_is_offered()`): routes with no scope or `'public' => true`, ids starting `auth.`, `push.` or `docs.`, ids ending `_post` (the IIS twins), and routes with `'mcp' => false` (below). An IIS POST twin's id must end in `_post`, or one action is two tools. Only routes the caller's scopes cover are listed.

## Which routes are no tools on purpose?
- The routes that carry `'mcp' => false` and why: `api-mcp-no-tool-routes.md`.

## How does tools/call run a route?
- In-process through `pg_mcp_route_run()`, with the checks of `integration.php`: scope, `api_validate_input()`, the handler. The handler leaves through `api_send()`/`api_fail()`; `api_send_hook()` hands the answer to the caller's writer (tools/call: a result, a refusal with `isError`).
- So a handler never reads `$_SERVER`, `$_GET` or `php://input` itself: it uses `api_request_input()` and `api_header()`, which the MCP endpoint fills through their setters (`X-Dry-Run` included), and `$_FILES` only while `api_send_hook() === null`.

## What besides tools?
- Resources (my-day, channel context, record refs) and prompts (channel_summary, weekly_report), answered by GET routes through that runner: a route that is no tool for the caller is no resource either (`mcp-resources-prompts.md`).

## Which address, and who may call?
- Given out at the site root, `site.com/mcp.php` (`router.php` hands it to `pinegrap/mcp.php`); the file in the software folder answers too. Both are in `pg_oauth_resource_addresses()['mcp']`, each with its own protected-resource document: a client compares its `resource` with the address it connected to. Compare resources with `pg_oauth_resource_key()`.
- Callers: an OAuth token issued for `mcp` (not one for `integration`), a team devices token or an application key, with the API's rate limit and request log. No session (`PG_NO_SESSION`); a call without credentials gets a 401 whose challenge says where to sign in.

## Which protocol?
- Streamable HTTP: one POST, one JSON-RPC message, one complete JSON answer; no session id, nothing streamed (no SSE) (`includes/mcp/protocol.php`).
- Two generations: 2026-07-28 (no `initialize`; the version in `MCP-Protocol-Version` or `_meta`, the method and the tool or prompt name or resource uri repeated in `Mcp-Method`/`Mcp-Name`, which must match the body) and 2024-11-05 to 2025-11-25, which open with `initialize`.
