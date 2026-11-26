---
name: erp-parasut-devhub-connection
description: Paraşüt through a Developer Portal client (#11694): provider and purpose of connected accounts, the company read on return, which token the requests use, Disconnect
module: parasut
---

## How does a Developer Portal client connect?
- Provider `includes/connect/providers/parasut.php` and site-owned purpose `parasut` of the connected accounts (`api/api-connected-accounts.md`): `id.parasut.com` with PKCE and state, scopes `openid offline_access`, `kc_idp_hint=parasut`, the client as form fields, return address `pg_connect_redirect_uri()` (`connect.php`). The framework does the exchange, the renewal under its lock and the sealed storage; nothing of it is in `parasut.php`.
- `identity => false`: one site connection labelled "Paraşüt", so the flash reads "Paraşüt is connected.". No `nonce` (no identity token is read), no `revoke_url` (the guide names none: Disconnect deletes the row). `listed => false`: no row of Account providers; its application is saved on Paraşüt's page.
- Back on the page, `connections.php` sees the flash's connection and runs `parasut_devhub_after_connect()`: `GET /v4/me?include=companies`, `parasut_connect_company('', saved, companies)`, writes `config.parasut_company_id`, keeps e-mail and company name in the sealed service entry `parasut_devhub` (with its `connection_id`), and answers `parasut_connect_report()`. Several companies and none saved: a warning, the number is typed under Advanced.
- The client lives in `connect_apps`, not `config.parasut_client_id`; `parasut_order_buttons_on()` counts the connection as the client.
- Connect again keeps the connection's id: `parasut_devhub_after_connect()` first drops the `parasut_devhub` entry, so a failed `/v4/me` read does not leave the earlier account's e-mail and company in the status line (#11741).
- Staging (`id-staging.parasut.com`) is the site's choice of environment: `erp-parasut-devhub-staging.md`.

## Which token do the requests use?
- `parasut_token_choice(parasut_devhub_connection())`: the site's connection whenever there is one, in any state (one that must be made again says so, no fall-back), else the older token below. `parasut_get_token()` then takes `pg_connect_access_token()`; `parasut_token_forget()` and `_parasut_token_after_401()` force its renewal. `parasut_connection_status()` without an owner reads the connection (`parasut_devhub_status()`, grant `devhub`).
- While a portal connection is there, the older Connect (a pasted code, or Save with a code) is refused before the code is exchanged (`parasut_code_connect_refusal()`): before, it reported "Connected with an authorization code" and moved the company number while the requests kept going with the portal connection (#11741).
- `parasut_disconnect()` ends both: the connection with its service entry, and the older token and secrets.

## Which Paraşüt user right do the requests need?
- The user the connection signs in as needs Settings › Users › Edit › "Uygulama Entegrasyonları" = "Yetki Var" in Paraşüt.
- Without it `/v4/me` answers 200, so the connect reports a company, but every company-scoped request answers 403 "User has no 3rd party integration permission for company: <id>" (seen on staging, 2026-11-27). Pinegrap does not warn of it yet: bug #11977.

Tests: `tests/parasut_devhub_connection_test.php` (authorize address, provider and purpose, token choice, status line, the older way's fold, the page's boxes). The guide's facts: `erp-parasut-devhub-client.md`; the older way: `erp-parasut-connection.md`.
