---
name: api-connected-accounts-github
description: GitHub in connected accounts (model A, no OpenID): identity from /user, a token that does not end, comma scopes, revoke and Check, the channel_links purpose with admin:repo_hook only
---

## How is GitHub connected?
- Provider `includes/connect/providers/github.php`: the site owner registers an OAuth app (GitHub › Settings › Developer settings › OAuth Apps) with the redirect address of Connected accounts; the card's setup steps say so. Model B (no app to register) waits for the broker (`plans/github-channel-connection.md` G3).
- The Connect button asks for the provider's `connect_purpose` (`channel_links`) instead of the base `account` purpose: an account with no scope would be of no use to GitHub's only feature.

## What differs from an OpenID provider?
- No ID token: who the account is comes from `userinfo_url` (`/user`) read through `identity_fields` (`id` => numeric id, `label` => `login`) in `pg_connect_identity()`.
- An OAuth app token does not end and comes without a refresh token: `refresh_optional`, `default_ttl` five years. GitHub withdraws it when the person revokes the app or after a year unused; the next 401 marks the account `reauth` ("The provider no longer accepts the permission.").
- The token answer lists scopes with commas: `scope_delimiter` => ',' (`pg_connect_scope_delimiter()`); other providers split on white space only, since a standard scope may contain a comma.
- Disconnect: `revoke_auth` `basic_json`, `DELETE /applications/{client_id}/token` with the app's id and secret (Basic) and `{"access_token": ...}`; `pg_connect_revoke_url()` puts the client ID in.
- Check on Connected accounts: a provider with `check_url` is called with its token (`pg_connect_check()`); forcing a renewal would mark a token that cannot renew as `reauth`.

## Which scope, and why?
- `channel_links` asks only `admin:repo_hook`: creates and deletes webhooks in public and private repositories, no code access. Listing private repositories would need `repo` (full access to every private repository), so a private repository is typed as `owner/name` (decided in G1 as least privilege; G2 builds the links).
- Organisations that restrict third-party apps must approve the OAuth app first (setup step).
