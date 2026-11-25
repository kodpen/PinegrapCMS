---
name: canva-api-sign-in
description: Canva Connect sign-in (read 2026-11-24): public app with Canva review, private app on Enterprise only, service app; OAuth with PKCE, single-use refresh tokens, scopes
---

## Which app kinds does Canva give?
- Public ("Anyone on Canva"): any Canva user; Canva reviews it against its submission checklist first. A public app that uses a preview API or feature does not pass review.
- Private ("Restricted to your Canva team"): only for a team on the Enterprise plan; the team's admins approve it.
- Service app: client credentials, no person signs in; a Business, Teams or Nonprofit team, one per team, creator with MFA or SSO; calls use paid API credits (since 2026-11-06, `service:*` scopes).
- The kind is chosen at creation and cannot be changed. Any Canva account can make an app in the Developer Portal; client ID and a secret shown once.

## Which addresses and rules does the OAuth flow use?
- Authorize: `https://www.canva.com/api/oauth/authorize` with `client_id`, `response_type=code`, `scope` (space-separated), `code_challenge` and `code_challenge_method=S256` (PKCE required), `state` recommended, `redirect_uri` optional when one is registered.
- Token: `POST https://api.canva.com/rest/v1/oauth/token` for the code and for refresh; Basic auth with client ID and secret recommended; from the server only (browsers are blocked by CORS).
- Revoke: the Revoke a token endpoint takes an access or a refresh token.
- The redirect address must be one registered on the app.

## How long does a token live?
- Access token: "a short period"; the answer gives the expiry.
- Refresh token: single use; every refresh returns a new one, which must be stored before the old one is lost.

## Which scopes does an import need?
- `design:meta:read` (list designs), `design:content:read` (pages, export), `profile:read` (who the account is); `brandtemplate:meta:read` / `brandtemplate:content:read` for brand templates.
- Scopes are explicit: `asset:write` does not give `asset:read`. Each must first be ticked on the app in the Developer Portal.
- No brand kit scope exists (`canva-api-brand.md`).
