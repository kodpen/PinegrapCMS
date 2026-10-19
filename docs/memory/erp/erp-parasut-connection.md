---
name: erp-parasut-connection
description: How Pinegrap is connected to Paraşüt (authorization code or password grant), where the token and secrets live, the Connect button and endpoint, company id discovery
---

## How is the Paraşüt connection made?
- Two grants, both in `includes/fn/parasut.php`, both ending in `parasut_get_token()`:
  1. **Authorization code** (preferred, no password stored): the Paraşüt card of the commerce settings has a link to `parasut_authorize_url()` (`/oauth/authorize?client_id=…&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code`). The operator signs in to Paraşüt, allows the application, Paraşüt prints a code; the operator pastes it in the "Authorization code" box and presses Connect.
  2. **Password grant** (the older way): user name + password boxes on the same card; used whenever the token has to be renewed and no refresh token is held.
- Connect posts the `parasut_*` boxes to `parasut_connect.php` (manager, form token, `format=json`; the same `button.pg-edoc-test` handler in `backend.src.js` with `data-pg-fields="parasut_"`). `parasut_connect_with_code()` exchanges the code, asks `GET /v4/me?include=companies` (`parasut_me()` / `_parasut_me_parse()`), then SAVES `config.parasut_client_id`, the secret into `parasut_credentials_enc` (password kept), `parasut_company_id`, and the sealed token. A code works once and for minutes; the answer says so when it is refused.
- Company id: the box may stay empty; Connect fills it when the user has exactly one company, lists the companies when there are several, and refuses an id the user cannot see. `erp_edoc_parasut_ping()` reads `/v4/me` too and names company and user in its answer.

## Where do the token and the secrets live?
- Secrets: `config.parasut_credentials_enc` (one encrypted JSON blob, `client_secret` + `password`); `_parasut_credentials()` decodes, `_parasut_credentials_store()` merges and writes.
- Token: sealed among the connected accounts' service tokens (`pg_connect_service_put('parasut')`): `access_token`, `refresh_token`, `expires_at`, `grant` (`code`|`password`), `email`, `company_name`, `connected_at`, and `owner` = md5(company|client id|user name). A token whose owner does not match the saved settings is ignored.
- The token endpoint is called with the client both as HTTP Basic and in the body; every grant carries `redirect_uri` = `PARASUT_REDIRECT_URI`.

## What does the card show?
- `parasut_connection_status()` (no call to Paraşüt) → "Connected with an authorization code / with the user name and password · e-mail · company · date" or "Not connected…". Built in `includes/settings/prep.php`, printed in `commerce.php` (Paraşüt block of `pgset-invoice`).
- After Connect the operator must close and reopen the settings before saving anything else on the page: the pane would otherwise write the boxes it loaded (an empty company id, an old client id) back.

## What happens when the refresh token dies?
- `invalid_grant` on refresh with no password on file → "The Paraşüt connection has expired … authorize once more"; with a password on file the password grant runs instead. Any other refresh failure (transport, 5xx) is passed on as it is.

## Tests
- `tests/parasut_test.php`: authorize URL, owner stamp, `/v4/me` reading.
