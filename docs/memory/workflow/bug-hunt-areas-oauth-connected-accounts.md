---
name: bug-hunt-areas-oauth-connected-accounts
description: The bug hunt of OAuth and connected accounts (2026-11-24, area 42): the two faults fixed, what was read clean
---

## Which area was hunted?
- Area 42, oauth-connected-accounts: `includes/fn/connect.php` (start, finish, tokens, renewal, Disconnect, service tokens), `connect.php`, the Check and Disconnect actions of `connections.php`, the provider files, and the work that uses an account (cloud backup, calendar writing, MCP sign-in). PR #PR.
- Asked for: the state checked on return, a renewal by two requests at once, a revoked or expired token reported and not retried for ever, secrets sealed and never shown again, who may connect or disconnect, a disconnect ending the scheduled work that used it.

## What did the hunt's PR fix?
- A request that waited 15 s for the renewal lock renewed anyway, beside the holder, with the same refresh token: a rotating provider marked the connection to be connected again. It now gives up for that call (`api/api-connected-accounts-renewal.md`).
- Disconnecting one Google row (or deleting a person) revoked the grant of another row holding the same Google account, so the site's backup or mail account stopped. Google is not told while another row holds the account (same file).
- Tests: `tests/connect_test.php`.

## What was read and found clean?
- State: 128-bit random, kept in the session, used once (removed before any check), tied to the person who started, 15 minutes; PKCE S256 and a nonce; RFC 9207 `iss` checked when sent or required; the return screen kept inside the panel (`pg_connect_return_path()`).
- Revoked or expired: `invalid_grant` marks `reauth` (but AADSTS65001), logged once; a `reauth` row answers at once from `last_error` with no provider call; GitHub's refused token marks `reauth` on the forced renewal after a 401; a timeout or 500 only writes `last_error`.
- Secrets: sealed with `ENCRYPTION_KEY`; `pg_connect_accounts()` / `pg_connect_account()` never select `secret`; connecting is off without a key.
- Rights: a site connection, Check and Disconnect need Manager; a person's own row is renewed only by that person (`pg_connect_renewal_allowed()`); a run-time provider (MCP) is refused on `connect.php` and connected from its own screen.
- After Disconnect: cloud backup reports "No … account is connected for backups"; calendar writing shows the Connect button and keeps its queue moving; GitHub channel links remove their webhooks first.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/api/api-connected-accounts.md
