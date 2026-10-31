---
name: indexnow-ping-result
description: The IndexNow ping in update_sitemap_and_ping() logs success only for HTTP 200/202; a refusal (400, 403, 422, 429) logs 'IndexNow ping failed: HTTP <code>'
module: seo
---

## When does the Site Log say the IndexNow ping was sent?
- Only when IndexNow answers 200 or 202 (`pg_indexnow_ping_accepted()` in `includes/fn/seo.php`).
- Any other answer logs `IndexNow ping failed: ` plus `HTTP <code>`; no answer (status 0) logs the cURL error (`pg_indexnow_ping_failure()`).
- The sitemap hash is still written after the attempt either way, so a refused ping is not retried until the sitemap changes again.

## What was the symptom?
- Every HTTP answer, including 403 (key not valid), 422 (host and key do not match) and 429, was logged as `IndexNow ping sent successfully.`, so an operator with a wrong key never saw why. The "any answer counts" line came from the move to `pg_curl_run()` (`curl-run-callers.md`), not from an owner decision.

## What pins it?
- `tests/exchange_rates_indexnow_result_test.php`.
