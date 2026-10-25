---
name: erp-parasut-connection
description: How Pinegrap connects to Paraşüt (authorization code or password grant), where the token and secrets live, the token's owner stamp, the Connect endpoint
---

## How is the Paraşüt connection made?
- Two grants, both in `includes/fn/parasut.php`, both ending in `parasut_get_token()`:
  1. **Authorization code** (preferred, no password stored): the commerce settings' Paraşüt card links to `parasut_authorize_url()` (`/oauth/authorize?client_id=…&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code`). The operator signs in, allows the application, pastes the printed code in the "Authorization code" box and presses Connect.
  2. **Password grant** (the older way): user name + password boxes under Advanced; used to renew when no refresh token is held.
- Connect posts the `parasut_*` boxes to `parasut_connect.php` (manager, form token, JSON; the `button.pg-edoc-test` handler with `data-pg-fields="parasut_"`). `parasut_connect_with_code()` exchanges the code, asks `GET /v4/me?include=companies` (`parasut_me()` / `_parasut_me_parse()`), then SAVES `config.parasut_client_id`, the secret into `parasut_credentials_enc` (password kept), `parasut_company_id`, and the sealed token. A code works once, for minutes.
- A code saved with the page (Save instead of Connect) is exchanged by `commerce.save.php` after the boxes are written; the page is saved either way.
- Company id: `parasut_connect_company()` (`erp-parasut-connect-card.md`). `erp_edoc_parasut_ping()` reads `/v4/me` too.

## Where do the token and the secrets live?
- Secrets: `config.parasut_credentials_enc` (one encrypted JSON blob, `client_secret` + `password`); `_parasut_credentials()` and `parasut_credentials_decode()` decode, `_parasut_credentials_store()` merges and writes.
- Token: sealed among the connected accounts' service tokens (`pg_connect_service_put('parasut')`): `access_token`, `refresh_token`, `expires_at`, `grant` (`code`|`password`), `email`, `company_name`, `connected_at`, `owner`. A token whose owner does not match the saved settings is ignored (`_parasut_token_owner_matches()`).
- The token endpoint gets the client as HTTP Basic and in the body; grants carry `redirect_uri` = `PARASUT_REDIRECT_URI`.

## What is the owner stamp?
- `_parasut_token_owner()`: md5(company|client id|user name) for a password token, md5(company|client id|) for a code token (#1599): the user name box serves the password grant only. Before, Connect stamped with the `PARASUT_USERNAME` read before Save wrote the box: Save with a code, or a later user name change, orphaned the token.
- Older code tokens: one stamped with an empty box has the new stamp; one stamped with a user name is read while that name stays saved, and the next renewal or `parasut_token_forget()` restamps it. No upgrade step.
- On a store connected by code, a user name and password saved under Advanced no longer replace the code token: it keeps renewing, and the password grant runs only when a refresh fails. Before #1599, saving another user name orphaned it and the password grant took over.

## What happens when the refresh token dies?
- Paraşüt rotates the refresh token: every refresh answers a new one and retires the old (API documentation; access token lives 2 h). The new one is saved over the old. A request that renewed in parallel leaves the other holding a retired token, so `invalid_grant` first re-reads the cache and uses one another request has just stored.
- `invalid_grant` on refresh with no password on file → "The Paraşüt connection has expired … authorize once more"; with a password on file the password grant runs instead. Any other refresh failure (transport, 5xx) is passed on as it is.

## Tests
- `tests/parasut_test.php`: authorize URL, owner stamp, `/v4/me` reading, status line, company number on save, throttle.
- `tests/parasut_connection_card_test.php`: code token stamp, card boxes, company choice, Connect's warning.

Related: docs/memory/erp/erp-parasut-connect-card.md (the card, Connect's answer, company choice)
