---
name: waf-db-guard-connection-failures
description: includes/db_guard.php rules: no fatal on DB connect error, only transient errors trip, guard runs before functions.php, circuit breaker with probing.
---

## Why does the WAF sit behind a DB guard (`includes/db_guard.php`)?
- In `init.php`, `db_connect()` is at line 225 and `waf_run('init')` at 737. When the connection pool is full the request dies at 225 and the firewall never runs: it cannot ban the attacker and the lock feeds itself. This took a site down (`forgot_password.php` + SMTP-waiting connections + `max_user_connections`).

## Which three rules came out of it?
- A connection error cannot be fatal. Since PHP 8.1 mysqli throws and `@` does not suppress it; the `else` branch of `db_connect()` was dead code for years. `try/catch` + 503 is mandatory. A stack trace per request is a second crash cause on disk-quota hosting, so the log writes ONE line per minute.
- Only TRANSIENT errors trip: 1040/1203/1226/2002/2003/2006/2013 back off; 1045/1049 show an error page. Waiting does not fix a wrong password, and a "try later" screen hides it from the only person who could fix it.
- That error page is a 500, as is the missing config table: `db_connect()` passes 500 to `output_error()`, `init.php` sets it before its message, `router_output_error()` sets it for every page it prints (front end, `get_file.php`, robots, sitemap). Before PR #1091 (issue #769) all of them were 200, so health.php and status-code monitors saw a broken site as up. `router_output_error()` looks for the config table only when the query works (`mysqli_num_rows(false)` was a PHP 8 TypeError on a dropped connection).
- `pg_db_guard_check()` runs BEFORE `functions.php`: by `db_connect()` the request has already parsed megabytes of code. The guard file is therefore self-contained: no DB, no `functions.php`, no `lang()`, no session (same constraint as `waf.php` and `get_file.php`).

## How does the circuit breaker work?
- A single error does not open the breaker, and an open breaker is probed. Connect via `pg_db_guard_connect()`: a quick transient error is retried twice more after 150/400 ms; an error over 1 s is NOT retried (unresponsive server, retrying multiplies hanging).
- While open, one request per 3 s probes (`pg_db_guard_claim_probe()`: timestamp in content + `LOCK_NB`). A mechanism failure stays WITHOUT a probe and does not let the flood in.
- The window is measured from mtime (last error); the reference and outage duration from file content (outage start). Never mix them, or every probe creates a new reference.
- Before this, one momentary rejection gave the whole site a 30 s 503.

Related: docs/memory/waf/waf-connection-held-work-and-silent-rejection.md
