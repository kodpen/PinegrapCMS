---
name: license-check
description: When the panel's licence check (license_check() in includes/fn/output.php) asks the licence server: once per session, 12 hours after an answer, 15 minutes after a failed attempt, once per request
---

## Where is the licence checked?
- `license_check($properties)` in `includes/fn/output.php`. The account menu calls it twice on every panel page: `'bar'` for the licence card and `'badge'` for the avatar dot (`account-menu.md`). It does nothing without a key (`SUBSCRIPTION_KEY`, from `config.subscription_key`).
- The result lives in the session (`$_SESSION['software']['settings']['license']`: `status`, `error_code`, `countdown`, `expiration_date_formatted`, `last_check`, `diag_*`). Saving a new key in Site Settings clears it (`includes/settings/general.save.php`), so the next page asks at once.

## When does it ask the server?
- `pg_license_check_due($force, $status, $last_check, $now)` decides: a forced check (`?check_license_again=1`, Manager or above) or a session that has not asked yet asks; an answer (any non-empty `status`, a refused key too) is trusted for 12 hours; a failed attempt (`status` `''`: no answer, not JSON, a proxy's HTML page) is retried after 15 minutes.
- `last_check` is stamped for every attempt, answered or not.
- Only the first call of a request may ask (a static flag in `license_check()`), so the card and the dot never cost two requests, a forced one included.

## Why the 15-minute wait?
- Each attempt may wait up to 10 seconds (`CURLOPT_TIMEOUT`). The old condition ignored `last_check` while `status` was empty, so with the server unreachable every panel page asked twice: three pages, six requests, and up to 20 seconds per page on a timeout. Issue #815.

## What guards it?
- `tests/output_test.php`: `test_output_license_check_due_*` (first and forced, the wait after a failure, the 12 hours).
