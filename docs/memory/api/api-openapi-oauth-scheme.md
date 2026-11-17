---
name: api-openapi-oauth-scheme
description: The OpenAPI document's oauth2 security scheme: named only while the site's OAuth application exists, the site's own authorize and token addresses, the ceiling's scopes, why operations do not list it
---

## When does the API document name the OAuth server?
- Only while `pg_oauth_app()` finds the site's OAuth application. Then `components.securitySchemes` gains `oauth2` beside `applicationKey` and `deviceToken`, whose names never change (a generated client refers to a scheme by its name).
- Without the application every OAuth address answers 404 (`pg_oauth_gate()`), so the scheme is left out rather than send a client to a server that is not there.

## What does the oauth2 scheme hold?
- One flow, `authorizationCode`: `authorizationUrl` and `tokenUrl` from `pg_oauth_endpoint('authorize'|'token')` (the configured host, never the Host header), `refreshUrl` the token address.
- `scopes`: the ceiling, `pg_oauth_ceiling($app)` (so never `webhooks:manage`), sorted, each described as "Read <group label>" / "Write <group label>" from `api_scope_groups()`. An empty ceiling still gives the map, encoded as `{}`.

## Where is it built and tested?
- `api_openapi_security_schemes($oauth = null)` in `includes/api/openapi.php` is pure: `$oauth` comes from `api_openapi_oauth_server()`, the one function that reads the site. `api_openapi_oauth_scheme()` builds the scheme.
- Tests: `tests/api_openapi_status_test.php` (with and without the application, empty ceiling). PR #10129.

## Why do operations not name oauth2 in their security?
- An OAuth connection's token is a Bearer token, which every non-public operation already accepts under `deviceToken`; its description says what a connection may do.
- Listing `oauth2` per operation would make each operation's security depend on the operator's ceiling and the route's scope, and a route outside the ceiling or marked `'mcp' => false` would be promised to a connection that is refused. A platform that imports the document reads the scheme from `components`.
