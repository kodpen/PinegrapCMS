---
name: api-connected-accounts
description: The site as an OAuth client (connected accounts, includes/fn/connect.php, 2026.4.10, model A): provider and purpose files, the Connect button, tokens and provider calls, refresh, service tokens
---

## What is the layer?
- The site connects to an outside account (Google, Microsoft, Dropbox...) as an OAuth client. Model A: each site registers its own application in the provider's console (model B and the connections still to build: `plans/reverse-oauth-leftovers.md`). Screen `connections.php` (Settings › Connections); one return address for every provider and purpose, `connect.php` (`pg_connect_redirect_uri()`). Tables `connect_apps` (one row per provider) and `connect_accounts` (10.72).
- The other direction, outside applications connecting to this site: `api-oauth-server.md`. Signing in with a provider: `social-sign-in.md`.

## How is a provider or a purpose added?
- A provider = `includes/connect/providers/<key>.php`: endpoints, `issuers`/`issuer_pattern`, `identity_scopes`, `authorize_params`, `token_per_scope`, `options`, `setup` steps. One whose ID token carries no nonce says `'nonce' => false`; one that revokes with the access token (Bearer, JSON `null` body) says `'revoke_auth' => 'bearer'` (Dropbox). A sign-in-only provider (Apple) carries `'accounts' => false`: Connected accounts does not list it and `pg_connect_start()` refuses it.
- A purpose = `includes/connect/purposes/<key>.php`: `label`, `description`, `owner` (`site`, `person` or `any`), `scopes` per provider.
- Neither changes the schema or `connect.php`. A provider whose endpoints are found at run time (an MCP server, by discovery) is built by a builder registered for a key prefix (`pg_connect_provider_builders()`).

## How does a screen connect an account?
- A form posting to `connect.php` with `pg_connect_start_fields($provider, $purpose, 'my_screen.php')` inside it. Connecting the same account again for another purpose merges the scopes into the same row (`connection_id`).
- Tokens are sealed with `ENCRYPTION_KEY` (`pg_connect_can_seal()`); without it the screen switches connecting off. Before the tables exist (`pg_connect_schema_ready()`) reads answer "no account".

## How does code use an account?
- Find it with `pg_connect_accounts(array('purpose' => ...))`, then call `pg_connect_api($id, 'POST', $url, $options, $scopes)` (a 401 renews once and repeats), or take only the token with `pg_connect_access_token($id, $scopes)`.
- Microsoft tokens are per resource: pass the call's scopes (e.g. `https://outlook.office.com/SMTP.Send`).
- Never read a refresh token. Renewal is locked per connection (`GET_LOCK`), and `invalid_grant` marks the account `reauth`.
- Tokens a server fetches for itself (Paraşüt, carriers' `client_credentials`): `pg_connect_service_get/put/delete($key)`, sealed in `connect_accounts` as provider `service`, never a file or `config` column.
- `pg_user_delete()` ends the person's rows via `pg_connect_forget_user()` (revoke, delete): a reused user id must not inherit them.
- Worked examples (mail, backups, response headers): `api-connected-accounts-usage.md`.

## How are an application's options and the Google fallback kept?
- An option may carry a `pattern` and an `invalid` text in its provider file (Microsoft `tenant`: `pg_connect_tenant_pattern()`). `connections.php` refuses a value that does not fit, and a stored one makes `pg_connect_app()` say `options_valid => false`, so `pg_connect_app_ready()` is false and the card names the problem; `pg_connect_endpoint()` answers `''` for it. An empty tenant is still `common`; a typo never is (#5987: a typo opened a single-tenant app to every account).
- Google without a `connect_apps` row uses the Sign in with Google application (`pg_signin_google_app()`). `pg_connect_app_save()` copies its secret into Google's first row when the client ID is the same and no secret is typed (#5988: an unchanged save stored an empty secret). "Use the Sign in with Google application" (`pg_connect_app_remove('google')`) deletes the row; accounts stay.
