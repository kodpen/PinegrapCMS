---
name: erp-parasut-devhub-client
description: Paraşüt's Developer Portal (DevHub) client as its guide describes it: the application and its fixed redirect address, sign-in at id.parasut.com with PKCE, token lifetimes, the Temel package's limits
---

## How is a client got?
- Read 2026-11-25 on `devhub.parasut.com/oauth-rehberi`. API access is applied for on the Developer Portal; client id and secret are shown on its "API Bilgilerim" page. The application asks for the redirect address: https, at most three, never changed afterwards.
- Two applications: a Paraşüt user for the own company (packages below), or a partner for a commercial product over several companies (a plan of its own).

## How does it sign in?
- Authorization: `https://id.parasut.com/realms/parasut/protocol/openid-connect/auth` with `response_type=code`, `scope=openid offline_access`, `state`, `code_challenge` and `code_challenge_method=S256` (PKCE is required), optional `kc_idp_hint=parasut` (skips the choice of identity provider). Token: `…/openid-connect/token` with `code_verifier`; the client as form fields or HTTP Basic. Staging signs in at `id-staging.parasut.com`.
- These clients have no out-of-band address and no password grant. Whether a client given by e-mail before the portal still works on `api.parasut.com/oauth/*` is not written anywhere.

## How long do the tokens live?
- The access token lives 300 s, the refresh token 30 days from its last use and is replaced on each refresh; without `offline_access` it dies when the person signs out of Paraşüt. `invalid_grant` means authorize again.

## What are the API's limits?
- The API is unchanged: `https://api.parasut.com/v4/{company}`. The free "Temel" package: one company, 10 requests in 10 s, 25,000 a month; a 429 says how long to wait; a client answering many 401 or 403 may be switched off.

## What does Pinegrap do with it?
- Since #11694 a portal client is the connected accounts' provider `parasut` (`erp-parasut-devhub-connection.md`). Not yet tried against Paraşüt: what the live answers show is added here once dev is connected (plan `plans/parasut-devhub-connection.md`).
