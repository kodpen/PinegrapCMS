---
name: api-cors-allow-list
description: Allowed browser origins card on Application Access (api_settings.php): the API CORS allow-list, preflight and Allow-Origin for a listed origin, token routes closed to unlisted ones
---

## Where is the list kept and edited?
- One `config_text` row, `api_cors_origins` (`api_cors_setting_name()`), one origin per line; no column. Read by `api_cors_origins()`, which parses it again so a hand-edited row cannot widen it.
- Edited in the "Allowed browser origins" card at the foot of Application Access's left column, drawn and saved only for roles 0 and 1 (administrator, Designer); a Manager neither sees it nor can post `api_action=cors_origins`.
- A save with any entry that is not an origin is refused whole, naming the entries, and the box shows the list as typed (session `api_cors_typed`, #11826); an unchanged list writes nothing; a change writes a Site Log line.

## What is an origin here?
- `https://` and a host (letters, digits, hyphens, dots, or a bracketed IPv6), an optional port; kept lower case, `:443` dropped (`api_cors_origin_normalize()`).
- Or a native shell's fixed origin (`api_cors_native_origins()`): `capacitor://localhost`, `ionic://localhost`, `tauri://localhost`, `http://localhost`. Capacitor on Android today is `https://localhost`, an https origin. The field's help names them.
- Never `*`, `null`, a path, a user part or plain `http` other than `http://localhost`.

## What does the API answer?
- List empty: nothing changes, no CORS header (the OPTIONS 204 stays bare).
- A listed `Origin`: every answer from `api_run()` after the install check, errors included, gets `Access-Control-Allow-Origin: <that origin>` and `Vary: Origin`; `api_send()` then adds `Access-Control-Expose-Headers` (`api-http-manners.md`). The preflight adds the methods, the request headers the API reads (not `X-Pinegrap-Key`/`-Secret`: a page signs with a bearer token) and `Max-Age: 600`.
- Never `Access-Control-Allow-Credentials`: the API reads no cookie.
- `GET /discovery` keeps its own `*`.

## Why are the token routes closed to an unlisted origin?
- `POST /auth/login`, `/auth/pair`, `/auth/refresh`, `/auth/logout` (the `'public' => true` rows) answer 403 `forbidden` to a request whose `Origin` is not listed (`api_cors_token_route_refused()`), so a page elsewhere cannot drive a sign-in through a visitor's browser. A call with no `Origin` (a server, most native clients) passes as before.
- The code is the catalogue's `forbidden` because `api_error_catalogue()` lives in `includes/api/schema.php`; a code of its own can be added there later.

## What does it not cover?
- The MCP endpoint (`pg_mcp_cors()`) and OAuth (`pg_oauth_preflight()`) keep their own rules. Under IIS a preflight may never reach PHP (`external-api-iis-environment.md`).
- No route was added: nothing in OpenAPI or the MCP tools changes.

Tests: `tests/api_cors_test.php`. PR #11170.
