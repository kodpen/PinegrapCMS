---
name: erp-parasut-connection
description: How Pinegrap is connected to Paraşüt (authorization code or password grant), where the token and secrets live, the Connect endpoint, company id discovery
---

## How is the Paraşüt connection made?
- Two grants, both in `includes/fn/parasut.php`, both ending in `parasut_get_token()`:
  1. **Authorization code** (preferred, no password stored): the Paraşüt card of the commerce settings has a link to `parasut_authorize_url()` (`/oauth/authorize?client_id=…&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code`). The operator signs in to Paraşüt, allows the application, Paraşüt prints a code; the operator pastes it in the "Authorization code" box and presses Connect.
  2. **Password grant** (the older way): user name + password boxes on the same card; used whenever the token has to be renewed and no refresh token is held.
- Connect posts the `parasut_*` boxes to `parasut_connect.php` (manager, form token, JSON; the `button.pg-edoc-test` handler with `data-pg-fields="parasut_"`). `parasut_connect_with_code()` exchanges the code, asks `GET /v4/me?include=companies` (`parasut_me()` / `_parasut_me_parse()`), then SAVES `config.parasut_client_id`, the secret into `parasut_credentials_enc` (password kept), `parasut_company_id`, and the sealed token. A code works once, for minutes.
- A code saved with the page (Save instead of Connect) is exchanged by `commerce.save.php` after the boxes are written: a notice on success, a warning on refusal, the page saved either way.
- Company id: the box may stay empty; Connect fills it when the user has exactly one company, lists the companies when there are several, and refuses an id the user cannot see. `erp_edoc_parasut_ping()` reads `/v4/me` too and names company and user in its answer.

## Where do the token and the secrets live?
- Secrets: `config.parasut_credentials_enc` (one encrypted JSON blob, `client_secret` + `password`); `_parasut_credentials()` decodes, `_parasut_credentials_store()` merges and writes.
- Token: sealed among the connected accounts' service tokens (`pg_connect_service_put('parasut')`): `access_token`, `refresh_token`, `expires_at`, `grant` (`code`|`password`), `email`, `company_name`, `connected_at`, and `owner` = md5(company|client id|user name). A token whose owner does not match the saved settings is ignored.
- The token endpoint gets the client as HTTP Basic and in the body; grants carry `redirect_uri` = `PARASUT_REDIRECT_URI`.

## What happens when the refresh token dies?
- Paraşüt rotates the refresh token: every refresh answers a new one and retires the old (API documentation; access token lives 2 h). The new one is saved over the old. A request that renewed in parallel leaves the other holding a retired token, so `invalid_grant` first re-reads the cache and uses a token another request has just stored.
- `invalid_grant` on refresh with no password on file → "The Paraşüt connection has expired … authorize once more"; with a password on file the password grant runs instead. Any other refresh failure (transport, 5xx) is passed on as it is.

## Tests
- `tests/parasut_test.php`: authorize URL, owner stamp, `/v4/me` reading, status line, company number on save, throttle.

Related: docs/memory/erp/erp-parasut-connect-card.md (the settings card and what Connect answers)
