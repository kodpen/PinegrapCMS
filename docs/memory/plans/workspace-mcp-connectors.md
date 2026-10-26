---
name: workspace-mcp-connectors
description: Plan (2026-10-23, 1, 3-6 #1463, 9, 13a #1471, 11-12 #1511, 2 #1521, 13 #1800, 7-8; answers 1, 3, 4): Workspace MCP client like Claude's connectors: protocol, card fixes, catalogue, URL-only add, API
---

## What is asked? (owner, 2026-10-23)
- The "MCP servers" card in Workspace settings is hard to follow: simplify it, with ready connectors as in Claude. Tried it; it did not work.

## What is there today?
- includes/mcp/client.php, includes/workspace/mcp*.php, workspace_mcp.php; workspace/workspace-mcp-servers-card.md.
- Why it fails (2026-10-24): 1) `WS_AI_ENDPOINT` gives 502 and Pinegrap AI answers each `@alias` outside agent mode; its card stays Ready. 2) Tools start off; with none on, the @ picker (`ws_mcp_boot()`), form run and agent mode skip the server; saved, they stay hidden; "Every tool that reads only" skips unannotated ones (DeepWiki, GitMCP). 3) "No credential" on OAuth servers reads "refused the credentials" (Linear, Notion, GitHub). 4) A trailing slash (307), /sse (405, 410): "could not be read". 5) GitMCP holds each stream 10 s.

## What is proposed? (not approved yet)
- Catalogue as data, includes/workspace/mcp_catalog.php (proposed): name, URL, sign-in, key page, icon, default rules; cards, one Connect.
- Custom: URL only; a probe picks the sign-in (none; 401 with `resource_metadata`: OAuth; else token); alias, header, client ID under Advanced. Rows: state, reason, checklist.
- API, admins, `'mcp' => false`: GET /workspace/mcp-servers, /workspace/mcp-catalog; POST /workspace/mcp-servers (key typed, sealed), /probe, /{id}/refresh, /{id}/tools; a dry run calls no server.

## Slices
No schema; one PR each.
1. `pg_mcp_client_send()`: a 401 whose `pg_mcp_client_bearer_challenge()` has `resource_metadata` returns first, naming Sign in to the service (OAuth) or a token (none sent), else today's text plus OAuth; negotiate adds `oauth_challenge`.
2. `api_http_request()` option `complete` (callable in CURLOPT_WRITEFUNCTION; errno 23 after a stop is success); `pg_mcp_client_post()` stops at the event answering its id (`pg_mcp_client_sse_complete()`, pure): GitMCP 10 s to under 1 s.
3. 3xx: "The MCP server moved to {var:1}." (https, same host); not followed.
4. /sse answered 404/405/410 names the /mcp address.
5. `pg_mcp_client_read()` takes an error with any id when HTTP >= 400.
6. `api_http_request()` returns `errno`; 28 says "took too long", not curl's words.
7-8 (#PR): random first id in `pg_mcp_client_next_id()`; `api_http_header_line()` joins repeated WWW-Authenticate.
9. Card: tools open after save while none is on; the add form keeps its input (not the secret) after an error; Pinegrap AI's last error; GET list, refresh.
10. Verify first (waf/): connect_client.php (CIMD) may meet `block_unknown_bots` or the country restriction; exempt by script as `waf_is_health_request()`; waf.php: PRs #1386, #1401.
11. Catalogue, its GET (Q1, Q5). 12. URL-only add, probe. 13. Three-state rules, bulk declare (Q3): panel #1471, API #1800.

## Which questions are answered? (owner, 2026-10-24: 1 a, 3 b, 4 b; 2, 5 open)
1. Catalogue: (a) bundled list, (b) MCP Registry fetch, (c) a plus a registry search → a.
2. Redirect: (a) never, name it, (b) follow once to https, same host, after `api_http_check_url()` → a.
3. After Connect: (a) all off (today), (b) read-only ones on, the rest by a warned "declare all read-only" → b.
4. Pinegrap AI set up but 5xx: (a) retry (today), (b) also post the form-run link (now only when not set up, `ws_mcp_form_hint()`) → b.
5. First list: (a) ~12 one-step (no account, OAuth with DCR/CIMD, GitHub token), (b) plus own-app ones (Slack, Asana) → a.

## Where to start?
- Owner: ai.pinegrap.com gives 502; meanwhile turn a tool on, use workspace_mcp.php.
- All in but 10 (WAF): next and last.
- Overlap: plans/workspace-agent-handles.md slice 5 (avatars), plans/workspace-mcp-capabilities.md (our server), plans/workspace-assistant-key-setup.md (Pinegrap AI card); GitHub OAuth: model B, plans/reverse-oauth-leftovers.md.
