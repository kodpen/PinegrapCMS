---
name: waf-inflight-request-cap
description: waf_inflight_* concurrent request cap on sensitive scripts: limit, 429 rule concurrent (no offence), file slots under data/temp, 120 s dead slot, fail-open, monitor mode.
---

## What does the concurrent request cap do?
- A rate limit counts requests per minute; what fills the pool is connections open AT THE SAME TIME (SMTP-waiting `forgot_password.php`). On sensitive scripts at most `waf_inflight_limit` (default 3, 0 disables) requests per subject may be open; extra get 429, rule `concurrent`, category `rate`, logged but NO offence (owner, 2026-10-27, #2201; `workflow/decisions-made-waf.md`): an office behind one NAT address opens four forms at once, so the refusal must not lead to a ban.
- Runs in the `init` phase with the same exemptions and the same `waf_rate_limit` switch as the sensitive rate limit. Subject = `waf_ip_subject()`.

## How is it implemented?
- File `data/temp/waf_inflight_<sha1(subject)>.txt`, one `token start` per line, read-decide-write under `flock(LOCK_EX)`, released with `register_shutdown_function` (also runs after fatal and `exit()`).
- A slot older than 120 s counts as dead (a hard-killed worker's leaked slot heals itself); the sweep deletes a file untouched for 10 min.
- The token is NOT the pid: pids repeat and `getmypid` may be in `disable_functions`.
- A failure of the mechanism itself (unwritable dir, lock not acquired) ALWAYS passes (fail-open).
- In monitor mode the slot is still taken so the log shows all rejections, not just the first.
- It does NOT run before the DB: subject resolution needs the trusted-proxy list, which needs the DB (see docs/memory/waf/waf-no-counters-before-db.md).

Related: docs/memory/waf/waf-ip-subject-ipv6-64.md
