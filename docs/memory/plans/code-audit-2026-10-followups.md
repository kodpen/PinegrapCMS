---
name: code-audit-2026-10-followups
description: Architecture items from the 2026-10-19 audit that no other plan holds: one outgoing HTTP wrapper, nested named functions, an explicit "who may write PHP" rule, CI checks
---

## Where does this come from?
- The refactor part of the 2026-10-19 audit; security items are in `security-findings-2026-10.md`, PHP deprecations and confirmed bugs in `php-compatibility-and-bugs.md`, older refactors in `refactor-candidates.md`. Nothing here is a decision; check `pinegrap-verilmis-kararlar` first.

## What is open? (size S/M/L)
1. **One outgoing HTTP wrapper.** `api_http_request()` (`includes/api/outbound/http.php`) already checks the address, pins it with `CURLOPT_RESOLVE` and refuses redirects, but 50+ hand-written cURL blocks remain: `submit_order.php` 21, `includes/fn/update.php`, `cloudflare.php`, `download_assistant.php`, and the importer's own redirect walk from #550. A `pg_curl_run()` beside `pg_curl_tls()` would carry the TLS flags, the address rule and the `curl_close()` deprecation (PHP 8.5) in one place. Payment and carrier calls stay outside `pg_curl_tls()` by decision. M.
2. **Named functions declared inside functions or branches** blind static analysis and are a redeclare risk: `includes/dashboard/widgets/widget_14.php:148`, `includes/fn/mail.php` 39-80, `includes/panel/explorer.php` 260-332, `router.php` 904/911. Closures, or a `function_exists()` guard where the declaration must stay. S.
3. **"Who may write PHP" is implicit in four evals** (`get_page_content.php` page eval, `dregion`, `custom_php` markers, hooks). One `pg_php_regions_allowed_sources()` and a settings default of "designer code only" would make it explicit. Waits on the eval re-ordering parked in `legacy-page-designer-retirement.md`. M.
4. **CI**: PHPStan level 0 and PHPCompatibility as phar steps in `php-checks.yml` (details in `php-compatibility-and-bugs.md`). S.

## Where to start?
- Item 2 is the cheapest and unblocks PHPStan on those files; item 1 after `payment-driver-layer.md` settles which gateway calls move.
