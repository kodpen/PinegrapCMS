---
name: waf-login-throttle
description: Sign-in throttle (pg_login_throttle_* in includes/fn/auth.php): address and account counters in waf_rate, lockout bucket, own switch, which endpoints must count, the 429 answer and its log lines.
---

## How are failed sign-ins counted?
- In `waf_rate`, against two subjects (`pg_login_throttle_subjects()`): the address (`waf_ip_subject()`: the /64 for IPv6) and the account name (`pg_login_throttle_account_subject()`). The address may fail five times the account's allowance: one person mistyping must not close the whole office, and an unlock by hand (Unlock Sign-in) reaches only account counters.
- `login_throttle_attempts` (10) per `login_throttle_minutes` (15); past that a separate lock bucket holds for `login_throttle_lockout` (60 min), so waiting for the window to roll does not give the allowance back.
- Account subject: `WEIGHT_STRING()` of the typed name under `user.user_username`'s collation, so every spelling `validate_login()` takes for one account (case, accents, full-width) shares a counter; PHP folding when no database answers. Lower case alone let "ádmin", "ADMİN" each open a fresh counter (the account limit never closed). Unlock clears both forms.
- A made-up user name counts like a real one (no account discovery). A success clears only the account's counters (`pg_login_throttle_pass()`): clearing the address would let one valid account reset the limit between guesses at others.

## Which endpoints must use it?
- Every endpoint that accepts a password: `pg_login_throttle_guard($name)` before the check, `pg_login_record_failure($name)` after a miss, the same buckets as the sign-in screens. A door without it bypasses the lock. `grep -rn "pg_login_throttle_guard("` lists the doors.
- `developer_lock.php` counts a wrong `DEVELOPER_PIN` against the signed-in user's name and address (#5166): the PIN is a few digits, and before it every value could be tried in turn.
- The sign-in question sits in front of the lock: docs/memory/waf/waf-login-captcha.md.
- Not a prototype share link's password: it opens no account, so its wrong answers count on its own row; the address bucket would lock the visitor's office out of sign-in (docs/memory/designer/designer-prototype-share-links.md).

## Does it depend on the firewall?
- No. Own switch `login_throttle` (default on, columns from 2026.4.4; `pg_login_throttle_ready()` hides it on a database without them). The firewall ships in Monitor mode and may be switched off to chase a false positive; neither may open the password door.
- Swept on failures (1 in 50 calls `waf_rate_sweep()`) and by `waf_sweep()`; both keep a bucket max(1 h, lockout, window) (`waf_rate_retention()`, `waf-bug-fixes-2026-11.md`).

## What does a locked visitor get?
- 429 with `Retry-After` (`pg_login_throttle_deny()`), one message whichever subject closed and whether the account exists; JSON when `API_USERNAME` is set, `api_fail()` inside the external API.
- The failure that closes a lock is recorded in `log_activity` and in `waf_log` (action `rate`, rule `login-lock`, category `login`, even with the firewall off); every refused attempt writes a `data/firewall.log` DENY line.

## Which address is never locked?
- One the firewall's allow list covers (`pg_login_throttle_address_subject()`, #2187): a shared office address whose other users fail fifty times would otherwise lock the allow-listed operator out for an hour. So Allow (on the `login-lock` row of the firewall log's suspects list) and the allow list release the address lock; there is no separate release action (`workflow/decisions-made-waf.md`). The account subject still counts.
- One `waf_ip_is_counted()` refuses: infrastructure, or a private or reserved address, the one a proxy not listed under Trusted Proxies gives every visitor (`waf-counter-subjects-and-endpoints.md`); the account subject still counts there. Before, only infrastructure was spared and one visitor's failures behind such a proxy locked everybody out.

Related: docs/memory/core/api-php-credentials-in-body.md
