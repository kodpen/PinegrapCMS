---
name: bug-hunt-areas-parasut-devhub-connect
description: The bug hunt in Paraşüt through a Developer Portal client (agent channel request 3016, 2026-11-26, #11741): the faults fixed, what was read clean and what was left
---

## Which area was it?
- Paraşüt through a Developer Portal client (#11694), before the owner tries the connect on dev: `includes/connect/providers/parasut.php`, `includes/connect/purposes/parasut.php`, `parasut_connect.php`, the token and company parts of `includes/fn/parasut.php`. Given by the architect in the agent channel, request 3016. Not in it: the e-document driver and the account sync.

## What was fixed?
- The older Connect (a pasted code, or Save with a code) ran while a portal connection was there: it reported "Connected with an authorization code" and moved the company number to the older account's, while every request kept going with the portal connection. It is now refused before the code is exchanged (`parasut_code_connect_refusal()`).
- Connect again keeps the connection's id, so when the new account's `/v4/me` read failed, the status line still named the earlier account's e-mail and company. `parasut_devhub_after_connect()` drops the details first.

## What was read and found clean?
- State and PKCE on the return (`pg_connect_finish()`): the state is taken out of the session before it is checked (no replay), names the user who started it (no foreign state), ends after 900 s; the verifier is always stored with it; `iss` is checked against `https://id.parasut.com/realms/parasut`.
- Who starts Connect and Disconnect: `connect.php` (manager, form token), Paraşüt's page (`connections_accounting_may_manage()`, the Accounting category: a manager too, form token), `parasut_connect.php` (manager, form token, JSON refusal before `validate_user()`).
- Two renewals at once: the portal token renews under the connection's lock and re-reads its row inside it; the older token under its own lock with the `invalid_grant` re-read.
- Disconnect deletes the connection row, its `parasut_devhub` entry, the older token and both secrets; the company number, client ids and user name stay (decided, `erp/erp-parasut-connect-card.md`).
- No secret or token reaches a log, an error line or the page: errors carry the provider's `error: description`, every line is escaped.

## What was left?
- Disconnect does not revoke the refresh token at Paraşüt: its guide names no revocation address (Keycloak's standard `…/openid-connect/revoke` is not documented by Paraşüt). Asked in #11741.
- A forced renewal after a 401 does not say which token was refused, so a request refused for a token another request already renewed renews once more. It works; only an extra token request.
- After Connect as a user with several companies, none of them the saved one, the saved number stays and a warning asks for the number under Advanced.
