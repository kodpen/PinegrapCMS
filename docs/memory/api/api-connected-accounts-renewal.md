---
name: api-connected-accounts-renewal
description: Connected accounts: renewal only under the connection's lock (a waiter that timed out gets busy), Google not revoked while another row holds the account
module: connect
---

## When is an access token renewed?
- `pg_connect_access_token()` takes the cached token while it has more than 120 s left; otherwise it waits up to 15 s for `GET_LOCK('pg_connect_<id>')`, reads the row again and asks `pg_connect_renew_step()` (pure): `cached` (a token stored meanwhile, or when forced one other than the refused one), `refresh`, or `busy`.
- A refused renewal: `invalid_grant` marks the row `reauth` (but Microsoft's AADSTS65001, `pg_connect_grant_lost()`) and a `reauth` row answers from `last_error` with no provider call; a timeout or 500 writes only `last_error`.
- `busy`: the lock was not had within the wait. The call fails with "The account is being renewed by another request. Please try again in a moment."; nothing is written to the row and the next call tries again.
- Why: the token request may take 20 s, longer than the wait. Before #11443 the waiter renewed beside the holder with the same refresh token; a provider that rotates refresh tokens (Microsoft, MCP servers under OAuth 2.1) refused the second as `invalid_grant` and the connection was marked `reauth` ("has to be connected again") though nothing was withdrawn.

## When does Disconnect tell the provider?
- `pg_connect_disconnect()` revokes the token (best effort), then deletes the row. `pg_connect_revoke_allowed($provider, $sharing)` (pure) decides.
- A provider with `'revoke_ends_grant' => true` (Google: revoking one token ends every token of that account for the client) is not told while another row has the same `provider` and `external_id`: the site's row and a person's own, or a person deleted (`pg_connect_forget_user()`) whose Google account is also the site's backup or mail account. Before #11443 that revocation stopped the other row too; it went `reauth` on its next renewal.
- The last row of the account revokes, so the permission still ends when nobody uses it.
- A provider that names no account (`'identity' => false`, MCP servers: every row's `external_id` is `account`) is always told: each owner signed in on their own.
- GitHub and Dropbox revoke only the one token, so they are always told.
