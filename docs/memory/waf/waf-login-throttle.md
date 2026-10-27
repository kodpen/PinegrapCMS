---
name: waf-login-throttle
description: Sign-in throttle (pg_login_throttle_* in includes/fn/auth.php): address and account counters in waf_rate, lockout bucket, own switch, which endpoints must count, the 429 answer and its log lines.
---

## How are failed sign-ins counted?
- In `waf_rate`, against two subjects (`pg_login_throttle_subjects()`): the address (`waf_ip_subject()`, so the /64 for IPv6; an unresolved infrastructure address is not counted) and the account name in lower case. The address may fail five times the account's allowance: one person mistyping must not close the whole office, and an unlock by hand reaches only account counters.
- `login_throttle_attempts` (10) per `login_throttle_minutes` (15); past that a separate lock bucket holds for `login_throttle_lockout` (60 min), so waiting for the window to roll does not give the allowance back.
- A made-up user name counts like a real one (no account discovery). A success clears only the account's counters (`pg_login_throttle_pass()`): clearing the address would let one valid account reset the limit between guesses at others.

## Which endpoints must use it?
- Every endpoint that accepts a password: `pg_login_throttle_guard($name)` before the check, `pg_login_record_failure($name)` after a miss, the same buckets as the sign-in screens. A door without it bypasses the lock. `grep -rn "pg_login_throttle_guard("` lists the doors today (sign-in screens, change password, `api.php` with a password in the body, the external API's device sign-in, MFA, ShipWorks, the install unlock form).
- The sign-in question sits in front of the lock: docs/memory/waf/waf-login-captcha.md.
- Not a prototype share link's password: it opens no account, so its wrong answers count on its own row; the address bucket would lock the visitor's office out of sign-in (docs/memory/designer/designer-prototype-share-links.md).

## Does it depend on the firewall?
- No. Own switch `login_throttle` (default on, columns from 2026.4.4; `pg_login_throttle_ready()` hides it on a database without them). The firewall ships in Monitor mode and may be switched off to chase a false positive; neither may open the password door.
- Its counters are swept on failures (1 in 50 calls `waf_rate_sweep()`), not by `waf_sweep()`, which never runs with the firewall off.

## What does a locked visitor get?
- 429 with `Retry-After` (`pg_login_throttle_deny()`), one message whichever subject closed and whether the account exists; JSON when `API_USERNAME` is set, `api_fail()` inside the external API.
- The failure that closes a lock is recorded in `log_activity` and in `waf_log` (action `rate`, rule `login-lock`, category `login`, even with the firewall off); every refused attempt writes a `data/firewall.log` DENY line.

## Which address is never locked?
- One `waf_ip_is_counted()` refuses: infrastructure, or a private or reserved address, the one a proxy not listed under Trusted Proxies gives every visitor (`waf-counter-subjects-and-endpoints.md`); the account subject still counts there. Before, only infrastructure was spared and one visitor's failures behind such a proxy locked everybody out.

Related: docs/memory/core/api-php-credentials-in-body.md
