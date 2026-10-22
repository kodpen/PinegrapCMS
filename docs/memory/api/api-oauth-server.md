---
name: api-oauth-server
description: The site as an OAuth 2.1 server (oauth.php, includes/oauth/, 2026.4.10): api_devices tokens, the oauth app, scopes, resources, error format, CIMD clients, switching clients off, ending connections
---

## What is an OAuth connection?
- The third caller: an outside application (an MCP client) a person connected on the consent screen. Its tokens ARE `api_devices` rows with `oauth_client_id > 0`. Never open a token table of its own: the revocation hooks (password, sign out everywhere, account deletion: `pg_auth_token_revoke_user()` → `api_devices_revoke_user()`) work on that table.
- The operator's switch and ceiling is an `api_apps` row of `kind = 'oauth'`; `pg_oauth_app()` takes the oldest active one. Without it every OAuth endpoint and `.well-known` document answers 404 (`pg_oauth_gate()`), as on a site with no server.

## Which scopes does a connection hold?
- What the person agreed to ∩ the ceiling ∩ the person's own rights, on every request (`pg_oauth_effective_scopes()`), so a demotion takes effect at once. `webhooks:manage` is never handed out this way (`pg_oauth_ceiling()`); `account:*` is not added by itself.

## How are resources kept apart? (RFC 8707)
- A token is issued for one resource, `integration` or `mcp` (`pg_oauth_resource_addresses()`; the first address of each is the one given out, `pg_oauth_resources()`). A new resource (a new MCP endpoint) gets a key and its addresses there, and its entry file defines `PG_OAUTH_RESOURCE`; `api_device_oauth_check()` refuses a token of another resource with 401.
- Compare resources with `pg_oauth_resource_key()`, never as written: the MCP server has two addresses, each a resource of its own name with one key.

## How do the OAuth endpoints answer?
- In OAuth's wire format, not the API's: `pg_oauth_fail($status, $error, $description)`; `error_description` is plain English ASCII (RFC 6749), never `lang()`.
- Code that answers a refresh in its own format uses `api_device_rotate_pair()`; `api_device_rotate()` fails in the API's format (`api_fail()`).

## What is a CIMD client?
- A `client_id` that is an https address is a client ID metadata document. `pg_oauth_client_resolve()` reads it only on the consent screen, after the person signed in to the panel (`api_http_request()`, 5 KB, no redirects, 30 reads per address per hour), keeps it as an `oauth_clients` row, reads it again daily and lets an old copy stand in for a week. The token endpoint never fetches: `pg_oauth_client()` reads the stored row.

## How is a client stopped, and a connection ended?
- Operator: `oauth_clients.status = 'disabled'` from Application Access → "Connected applications" (`pg_oauth_client_set_status()`): no new connection, its tokens refused, its connections kept and given back when it is switched on. The sweep (`pg_oauth_clients_sweep()`) never deletes a disabled row and a disabled CIMD copy is not read again. Deleting a client (`pg_oauth_client_delete()`) ends its connections, and it may register itself again.
- Person: `connected_apps.php` lists and ends their own connections. Code that ends one from a form id checks the owner in the query (`pg_oauth_connection_end()`).
- Client compatibility rules: `api-oauth-client-compat.md`.
