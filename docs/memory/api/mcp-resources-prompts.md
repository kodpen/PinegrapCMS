---
name: mcp-resources-prompts
description: MCP resources (pinegrap://workspace/my-day, channels/{id}/context, refs/{type}/{id}) read through their GET routes; prompts channel_summary and weekly_report; who sees them, errors, caching
---

## Which resources are there, and how is one read?
- `pg_mcp_resource_kinds()` (`includes/mcp/resources.php`): `pinegrap://workspace/my-day` (GET /workspace/my-day), templates `…/channels/{id}/context` (GET /workspace/channels/{id}/context) and `…/refs/{type}/{id}` (GET /workspace/refs?type=&id=). A template variable is the route parameter of that name, path or query.
- A read runs the route through `pg_mcp_route_run()`, the runner of tools/call (scope, `api_validate_input()`, handler, `api_send_hook()`): scope, OAuth ceiling, rights, channel membership and Claude's channel gate are the tool's (record refs too: workspace/workspace-claude-key-reads.md). Result: one text item, `application/json`, the route's JSON body, `uri` as sent.
- A new resource is a row there over a GET route; `test_mcp_resources_templates_match_their_routes` checks its variables are route parameters and the required ones come from the URI.

## Who sees which resource?
- Listed and readable only while its route is one of the caller's tools (`pg_mcp_routes_for()`: offered, not `'mcp' => false`, in the caller's scopes). Giving a route `'mcp' => false` takes its resource and the prompts that need it away.

## How is a uri matched?
- `pg_mcp_resource_parse()`: scheme and host in any case, the path as written; id `[1-9][0-9]{0,17}`, type `[a-z][a-z_]{0,31}`; no query, fragment, extra part or percent-decoding (`%35` is not 5); 512 bytes at most.

## Which errors and cache hints?
- A uri that names nothing, a route not offered or out of scope, any 4xx of the route: "Resource not found", -32602 in 2026-07-28 (its spec), -32002 before; `data.uri`, and `data.error` = the route's error object exactly as the tool shows it, its code included: Claude's key gets 403 `forbidden` for a channel it may not be asked in and 404 `not_found` for a missing one, as over the tool and REST. 5xx: -32603. No uri: -32602. HTTP 200.
- 2026-07-28: `Mcp-Name` carries the uri on resources/read and is required; `=?base64?…?=` values are decoded before the compare (`pg_mcp_header_name_decode()`), tool and prompt names too.
- 2026-07-28 caching (`pg_mcp_cache_hints()`): lists `ttlMs` 300000, reads 0, `cacheScope` private. No subscriptions; `listChanged` false.
- Nothing is paged and no `nextCursor` is handed out, so a cursor (other than null or '') on any of the four lists is -32602 "Invalid params: unknown cursor." in both generations, as the pagination pages ask (`pg_mcp_list_answer()`); before, the whole list came back.

## Which prompts are there?
- `includes/mcp/prompts.php`: `channel_summary` (channel_id required), `weekly_report` (channel_id optional), offered while the resources they need are readable (`needs`).
- channel_summary runs the context route: on 2xx the context follows as a second message, an embedded resource; otherwise text only, alike for a missing or unreadable channel. weekly_report names my-day and, with channel_id and the context readable, the channel; it embeds nothing.
- English texts that name each resource and its tool and end with `pg_ai_prompt_rules('assistant')`. Unknown prompt or argument, a missing one, an id that is not a positive whole number: -32602.

## Where is it tested?
- `tests/mcp_resources_test.php`. The decisions are pure functions it runs: `pg_mcp_list_answer()`, `pg_mcp_resource_target()` (before the route), `pg_mcp_resource_answer()` (after it), `pg_mcp_prompt_embedded()` (2xx only); the request functions only write what they return (`pg_mcp_answer()`).
- End to end on a scratch install with an application key (both generations, a private channel the owner left, a key without workspace:read).
