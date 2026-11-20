---
name: api-app-token
description: An application key and its secret as one token (Authorization: Bearer pgkt_<key>.<secret>, feature U10): format, where it is read, same path as HTTP Basic, never from the query string
---

## What is the token?
- `pgkt_` + the key + `.` + the secret (`api_app_token()`, `includes/api/keys.php`). Nothing new is stored: it is built from the key and the secret the moment the secret is shown, and a key without a secret has none.
- A key or secret holds only letters, digits and `_`, so the first dot ends the key (`api_app_token_parse()`).
- For a client whose connection form asks for an address and a single token instead of a user name and a password.

## How is it told apart from a device or OAuth token?
- By the prefix alone (`api_app_token_is()`): device and OAuth access tokens start `pgat_`. `api_authenticate()` sends only a non-`pgkt_` Bearer to `api_device_authenticate()`.

## What does a call with it go through?
- Exactly HTTP Basic's path: `api_read_credentials()` reads the token first and returns the same key and secret Basic would, so the key lookup, `api_secret_matches()`, the rate limit, the log line and the firewall's count are the same.
- A wrong secret, an unknown key, or a `pgkt_` value that is not a key and a secret: `api_offence()` and 401 `unauthorized`.
- The previous secret keeps answering through its grace (Application Access gives 24 h) via the shared `api_secret_hash_matches_app()`, so the old token does too.
- The API (`bootstrap.php`) and the MCP address (`includes/mcp/protocol.php`) both call `api_authenticate()`, so both take it.

## Where is it never read?
- The body or the query string: only the `Authorization` header (`api_read_bearer()`). A test key's `api_key` query read stays as it was, and a token there is ignored.

## Where is it described?
- OpenAPI: the `applicationKey` scheme's text names the token; the `deviceToken` text says a `pgkt_` Bearer is an application key. No new scheme.
- The sheet's field that shows the token comes with step AA3 of the app-access redraw plan.
