---
name: waf-connection-held-work-and-silent-rejection
description: Endpoints holding a DB connection while waiting on external services, rate limits vs connection-seconds, and always logging rejections.
---

## May expensive work run while holding a DB connection?
- No. `email()`, external API calls and long loops each get a limit first (the `pg_password_reset_guard()` pattern). The login throttle counts FAILURES; reset/send endpoints have no failure to count, so REQUEST RATE is counted.

## Why is the rate limit not enough?
- The rate limit counts requests, but the scarce resource is connection-seconds. `waf_rate` limits (sensitive: 30/min) assume cheap requests. An endpoint waiting on SMTP: 20/min x 30 s = 10 concurrent connections, so it is possible to fill the pool while staying under the limit.
- A new endpoint that holds a connection while waiting on something external gets its own limit; do not trust the general rate limit.

## Must a refusing protection be logged?
- Yes: a protection that rejects silently is half a protection. In the incident the attack never reached `waf_log` (the table cannot be written while the pool is full) and the operator noticed days later from the PHP error log.
- When adding a new limit/gate, also add a `waf_log_event` call.
- The outage record (`db-unavailable`) is written with SCORE 0 and BEFORE the mode check: an outage is a fact, not a security decision. If scored, the first visitor after the site recovers would be banned.

Related: docs/memory/waf/waf-inflight-request-cap.md
