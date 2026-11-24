---
name: figma-api-sign-in
description: Figma REST sign-in (read 2026-11-24): OAuth app (draft, private, public with review), personal and plan access tokens, scopes, PKCE, token lifetimes
---

## Which ways in does Figma give?
- OAuth 2 app: acts for the person who allows it. Created under My apps (`figma.com/developers/apps`) for one team or organization; client ID and secret, the secret shown once.
- Personal access token: made by the person in Settings › Security › Generate new token, with an expiry and the scopes chosen; sent as the `X-Figma-Token` header; acts as that person on every file they can open; revoked at once from the same tab.
- Plan access token: Organization and Enterprise plans only, made by an org admin, not tied to a person, expires within a year at most.

## Which OAuth app kinds exist, and who may sign in?
- Draft: only its creator and the plan's admins can try it.
- Private: only people in the team or organization it was made in; published without Figma review.
- Public: anyone; Figma reviews it first (description and testing instructions).
- Apps made before 2025-09-23 had to be re-published by 2025-11-17 with an owner, public/private and granular scopes, or fell back to draft and stopped calling the API.
- For a self-hosted CMS this means: one public app needs Figma's review and a fixed list of redirect addresses, while every Pinegrap site has its own address; a private app per site works only for that owner's own team (`figma-canva-census.md`).

## Which addresses and rules does the OAuth flow use?
- Authorize: `https://www.figma.com/oauth` with `client_id`, `redirect_uri`, `scope`, `state`, `response_type=code`, optional `code_challenge` (PKCE, S256 only, recommended). Must open in a browser; a WebView is refused.
- Token: `POST https://api.figma.com/v1/oauth/token`, HTTP Basic (client ID and secret), form `redirect_uri`, `code`, `grant_type=authorization_code`, optional `code_verifier`. A code lives 30 seconds.
- Refresh: `POST https://api.figma.com/v1/oauth/refresh` with Basic and `refresh_token`.
- `redirect_uri` must be one registered on the app and equal in both calls.

## How long does a token live?
- Access token: 90 days by default; the answer carries `expires_in`.
- Refresh token: reusable. Figma keeps one access token per app per person, so a refresh voids the previous access token; always store the newest.
- The numeric `user_id` is deprecated for `user_id_string`.

## Which scopes does an import need?
- `file_content:read`: file JSON, nodes, rendered images, image fills.
- `file_metadata:read`: file metadata. `current_user:read`: name, email, picture (who the account is).
- `library_content:read`, `team_library_content:read`, `library_assets:read`: published styles and components.
- `file_variables:read`: variables, Enterprise only (`figma-api-styles-variables.md`).
- `files:read` and `file_read` are deprecated and being removed; use the granular scopes.
