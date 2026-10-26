---
name: api-mcp-client
description: The site as an MCP client (includes/mcp/client.php, the Workspace's MCP servers): what a refusal says (OAuth challenge, moved address, old /sse address, JSON-RPC error, timeout) and why
---

## Where are the error texts made?
- `pg_mcp_client_send()`: `pg_mcp_client_transport_text($error, $errno)` when nothing came back (status 0), else the server's JSON-RPC error, else the cases below, else `pg_mcp_client_status_text()` by status. The Workspace keeps it in `ws_mcp_servers.last_error` (printed with `h()`); `ws_mcp_error_text()` still turns a 401 on an OAuth server into "connect again".
- Tests: `tests/mcp_client_test.php`, a stand-in `transport` on the connection, no network.

## What does a 401 with an OAuth challenge say?
- Symptom: Linear, Notion and GitHub saved with "No credential" read "refused the credentials. Check the token or the header."
- A 401 whose Bearer challenge names `resource_metadata` (RFC 9728) is answered before the body is read, with `oauth` true (`oauth_challenge` from `pg_mcp_client_negotiate()`). No credential sent: "This MCP server asks for a sign-in: … choose Sign in to the service (OAuth), or Access token (Bearer) …". One sent: "… refused the credentials. Check the token or the header, or choose Sign in to the service (OAuth) …": GitHub and Linear answer a wrong token with the same challenge, and GitHub has no dynamic registration, so OAuth is offered, not pushed.
- Without the challenge the old text stays. Status and headers stay in the answer, so `pg_mcp_client_discover_auth()` keeps its probe. Not covered: GitHub's 400 to a malformed token.

## What does a redirect say?
- Symptom: "could not be read (HTTP 307)" for `https://mcp.deepwiki.com/mcp/`, whose trailing slash gets `Location: http://mcp.deepwiki.com/mcp`.
- 301/302/303/307/308 (`pg_mcp_client_moved_to()`): "The MCP server moved to {address}. Enter that address instead." A relative Location is read against the address; http on the same host is named as https. Never followed: `api_http_request()` refuses redirects, and the new address is checked once entered. No usable Location, or the same address: the status text.

## What does an old /sse address say?
- Symptom: "(HTTP 405)" (DeepWiki) or "(HTTP 410)" (Cloudflare docs). The HTTP+SSE transport is not spoken.
- A path ending in `/sse` answered 404/405/410 (`pg_mcp_client_sse_address()`): "This is an old SSE address. The address that works here usually ends in /mcp: {that /sse made /mcp}".

## Why is a query shown as "?…"?
- `pg_mcp_client_shown_address()` names origin and path only: some services take a key in the query, and a refusal can be posted in a channel (an ask's error).

## Which JSON-RPC error is read?
- The answer with the request's id, or an error with no or a null id; with HTTP >= 400 an error with any id. FastMCP (DeepWiki) answers 400 with id "server-error" and its reason ("Unsupported protocol version"), which read "(HTTP 400)" before. With a 2xx another id's error is still not taken.

## What does a timeout say?
- Symptom: curl's "Operation timed out after 5001 milliseconds with 184 bytes received".
- `api_http_request()` answers `errno` (`curl_errno()`, else 0); `pg_mcp_client_post()` and `pg_mcp_client_register()` pass it on. 28: "The MCP server took too long to answer.", 7: "could not be reached", 6: "does not resolve"; any other keeps curl's words and the TLS hint.

Related: api-mcp-server.md (the site's own server), api-connected-accounts-usage.md (`api_http_request()`).

## Which id does a request carry?
- `pg_mcp_client_next_id()` starts each PHP request at a random number (`random_int()`) and counts up, so two requests of the site in one MCP session (a page and a background run) do not both send id 1. Never seen failing; done as a precaution (#1815).
