---
name: parasut-devhub-connection
description: Plan (2026-11-25): connecting Paraşüt with a client from its Developer Portal (DevHub): the registered redirect address, sign-in at id.parasut.com with PKCE, short tokens; step PD1
---

## What changed at Paraşüt?
- A client is now got on Paraşüt's Developer Portal and signs in another way; what its guide says is in `erp/erp-parasut-devhub-client.md`: a redirect address fixed at the application, `id.parasut.com` with PKCE, a 300 s access token, no out-of-band address and no password grant.

## Why is it work?
- Pinegrap sends `redirect_uri=urn:ietf:wg:oauth:2.0:oob` to `api.parasut.com/oauth/authorize` and has the code pasted, or uses the password grant (`erp/erp-parasut-connection.md`). A portal client can do neither, and the Paraşüt page names no address to enter in the application. The demo company's key ended on 2026-10-25, so its next key is a portal client (`erp-parasut-open-work.md`).

## What is decided?
- Architect (2026-11-25): the redirect address is the site's one return address, `pg_connect_redirect_uri()` (`https://<site>/pinegrap/connect.php`), as for every provider (`api/api-connected-accounts.md`). On dev it is `https://dev.pinegrap.com/pinegrap/connect.php`, the address given to the owner for his application.
- Paraşüt becomes a provider of connected accounts (`includes/connect/providers/parasut.php`) with a site-owned purpose of its own: the framework's PKCE, state, token exchange, renewal under its lock and sealed storage are used, nothing of them written again. Each site registers its own client (model A).
- `parasut_get_token()` takes the site's Paraşüt connection when there is one (`pg_connect_access_token()`), else the token of the older way, unchanged. The company comes from `GET /v4/me?include=companies` on return, through `parasut_connect_company()`.
- The older way (pasted code, user name and password) stays for the sites running on it and is no longer the first thing offered.
- No table or column; a step that needs one asks first.

## How is the screen put together?
- Paraşüt's page of Connections, "Connection details": first the application boxes as the other providers' pages draw them (`connections_app_form()`): "Client ID", "Client secret", "Redirect address" (read-only, with "Copy"), and under it the help "Enter this address as the Redirect URL of your application on Paraşüt's Developer Portal. It cannot be changed after the application.", then "Save".
- With a saved client: "Connect" sends the browser to Paraşüt and brings it back to this page with "Paraşüt is connected." and the company's name in the status line; the company number box under "Advanced" fills itself.
- The three steps of the older way move into a folded "A client from before the Developer Portal", open only while the site's token is of that kind.
- "Disconnect" ends whichever connection the site has.

## Steps
- PD1 (M): the provider and its purpose, the token choice, the page as described. Tests: the authorize address (return address, scope, PKCE, state), the token choice, the page's boxes. What no test reaches is said in the PR: a connect against Paraşüt, made on dev with the owner's client once the step has landed.
- Claim as `[plan:parasut-devhub-connection] PD1 — <topic>`. When PD1 has landed and dev is connected, what the live answers showed goes into `erp/erp-parasut-connection.md` and `erp-parasut-devhub-client.md`, and this plan is deleted.
