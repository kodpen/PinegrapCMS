---
name: social-sign-in-providers
description: Adding a sign-in provider: the 'signin' block of its provider file (order, scopes, pkce, return, claims, Apple's JWT secret), its switch, what is stored (no token), the old user_google_id column.
---

## Where is a sign-in provider defined?
- In its connected-accounts provider file `includes/connect/providers/<key>.php` (`api/api-connected-accounts.md`): a `signin` block makes it a sign-in provider for `includes/fn/signin.php`. Google, Microsoft and Apple have one today; Apple signs in only (`'accounts' => false`).
- The block's keys (explained in the header of `includes/fn/signin.php`):
  - `order`: where its button stands; `scopes`: what is asked for.
  - `authorize_params`: replace the provider's own on the sign-in request (Google: `access_type=online`, `prompt=select_account`).
  - `return`: the root script the provider sends people back to, `signin.php` when absent (Google: `google_auth.php`).
  - `pkce`: false when the provider does not take PKCE (Apple).
  - `subject_claims`: claims that together name the person instead of `sub` (Microsoft: `tid`, `oid`).
  - `verified_tenants` / `verified_claims`: when an e-mail claim counts as verified (Microsoft); an address the provider does not vouch for never matches an account.
  - `client_secret => 'apple_jwt'`: the secret is a JWT signed with a key (Apple also needs Team ID and Key ID before its button shows).
  - `user_field`: the posted field carrying the name on a first sign-in (Apple's `user`).

## What else does a new provider need?
- Its switch: `config.signin_providers` lists the providers whose buttons are on (Settings › Security, `includes/settings/security.save.php`); the application is its `connect_apps` row. Google keeps its own: `config.oauth_google_enabled` and the application in `config.oauth_google_*`, so a site that had Google sign-in before needs no change in Google Cloud Console.
- Nothing else: flow, matching, failure screen and buttons are shared (`social-sign-in.md`); buttons only through `pg_signin_buttons()`.

## What is stored?
- The identity, in `user_identities` (2026.4.10, step 10.74). No token is kept: signing in only needs to know once who the person is.
- Google identities moved there from `user.user_google_id` with 10.75. The column is read only while it exists (`pg_schema_has()`), for a link that was not moved yet; such a link moves the first time it signs in.

Related: docs/memory/core/social-sign-in.md, docs/memory/core/social-sign-in-connect.md
