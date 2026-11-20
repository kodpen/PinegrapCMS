---
name: mcp-compact-list
description: The MCP server's compact list (mcp_compact.php, AG7): guide_read, tools_find, tools_describe, tools_read, tools_write and the money tools by name; one OAuth resource with mcp.php; refusal codes; sizes
---

## What is the compact list?
- A second address of the MCP server, `mcp_compact.php` (site root via `router.php`, and the file in the software folder). `PG_MCP_COMPACT` is set by the file, which then runs `mcp.php`.
- Its `tools/list` (`pg_mcp_compact_tools_list()`, `includes/mcp/tools.php`): `guide_read`, `tools_find`, `tools_describe`, `tools_read`, `tools_write`, then every route marked `'moves_money' => true` the caller holds, under its own name. Resources and prompts are the full address's.
- Why: the full list is about 857 KB with every module on; the compact one 4,161 bytes without money tools (test limit 8 KB, `tests/mcp_compact_test.php`). Built in plan ai-guide step AG7 (features G13, G14).

## What do the four tools do?
- `tools_find` (words, area): at most 20 names, best match first, with the description's first sentence (200 characters); `more` true when others match. Area = the route id's first part; the enum lists the caller's areas. Ranking: words matched, then name > summary > description; a plural's s is dropped.
- `tools_describe` (names, 1 to 5): each tool's description, input schema and `run_with` (`tools_read`, `tools_write` or `own_name`); `unknown` lists names the caller does not hold.
- `tools_read` (name, arguments): GET routes only. `tools_write` (name, arguments, dry_run): the rest; a top-level `dry_run` goes into the arguments, read as the tool reads it.
- Both run the route through `pg_mcp_route_run()`: its own scope, validation, dry run, rate limit and request log. `pg_mcp_compact_target()` decides without running (tested).

## How are wrong calls refused?
- As the API's error object in a result with `isError`, through `api_fail()` with the send hook: `tool_writes` (a write through `tools_read`), `tool_reads` (a GET through `tools_write`), `tool_moves_money` (a money route through `tools_write`: call it by its own name, so the client's question names the action), `not_found` (no such tool for the caller), `validation_failed` (field named).
- A tool called by its own name runs on both addresses. The four meta names exist only on the compact address.

## Which OAuth resource is it?
- The same `mcp` key: `pg_oauth_resource_addresses()['mcp']` holds mcp.php's two addresses then the compact two (`pg_oauth_mcp_compact_addresses()`); a token for one is good on all four. Each has its own protected-resource document at `/.well-known/oauth-protected-resource/<path>`, `resource_name` "<site> MCP (compact)". The address given out (`pg_oauth_resources()`) stays mcp.php.
- `waf.php` lists `mcp_compact.php` beside `mcp.php` in the API exemptions.

## What do the opening instructions say there?
- `pg_mcp_instructions_tools(true)` swaps the "Each tool is an endpoint" sentence for how the four tools are used: 3,042 bytes in the test setup, limit 3,072.

## Where does a person find it?
- Not yet on a panel screen: the OAuth connection guide on Application Access gets it with step AA3 of plan app-access-redraw. Architect (2026-11-20): AG7 does not enter `api_settings.php`, so the screen has one PR at a time.
