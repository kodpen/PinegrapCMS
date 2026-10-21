---
name: oauth-authorize-state
description: OAuth authorize (includes/oauth/authorize.php): state is handed back exactly as sent, never cut; a state over 4096 bytes is refused with invalid_request and the same state
---

## What happens to state?
- `pg_oauth_authorize()` reads `state` as sent, carries it through the consent screen's hidden field and returns it on every answer to the client (`pg_oauth_authorize_return()`: code or error, with `iss`). RFC 6749 4.1.2: the client compares it whole, often a signed or encrypted value.
- Nothing about it is stored; the code row does not keep it.

## Why is there a ceiling and no cut?
- Symptom (#864): state was `mb_substr(…, 0, 1000)`; a 1403-character signed state came back as 1000 characters on the code and on the error redirect, so the client's state check failed in silence.
- Now `pg_oauth_state_is_acceptable()` / `pg_oauth_state_max()` (4096 bytes, `includes/oauth/rules.php`, pure, `tests/oauth_test.php`). Longer: `invalid_request` "The state is longer than 4096 bytes." to the client, with the whole state, right after the redirect address is known to be the client's.
- The request URL already carried the state, so the redirect back fits the same servers.
