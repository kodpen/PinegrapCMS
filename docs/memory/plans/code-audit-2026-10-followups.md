---
name: code-audit-2026-10-followups
description: Architecture items from the 2026-10-19 audit that no other plan holds: one outgoing HTTP wrapper, an explicit "who may write PHP" rule; CI checks (PR #673) and nested named functions (PR #972) done
---

## Where does this come from?
- The refactor part of the 2026-10-19 audit; security items are in `security-findings-2026-10.md`, PHP deprecations and confirmed bugs in `php-compatibility-and-bugs.md`, older refactors in `refactor-candidates.md`. Nothing here is a decision; check `workflow/decisions-made.md` first.

## What is open? (size S/M/L)
1. **One outgoing HTTP wrapper.** `api_http_request()` (`includes/api/outbound/http.php`) already checks the address, pins it with `CURLOPT_RESOLVE` and refuses redirects, but 50+ hand-written cURL blocks remain: `submit_order.php` 17, `includes/fn/update.php`, `cloudflare.php`, `download_assistant.php`, and the importer's own redirect walk from #550. Payment gateways and carriers already use `pg_curl_tls()`; Stripe and iyzico stay in their SDKs. M.
   - 1a done (PR #1884): `pg_curl_run()` beside `pg_curl_tls()` (`platform/update-tls-verification.md`) carries the fixed vendor-host calls: both update-server version checks, the CA bundle download, Cloudflare (`cf_request()`, `cf_graphql()`), the integrity reference. The CA store probe in `pg_ca_bundle_config_state()` stays a raw handle: it tests the host's own store, which `ALLOW_INSECURE_UPDATE_TLS` would switch off.
   - Next (1b): the other single-host calls listed under "Which other calls go through pg_curl_tls()?" in `platform/update-tls-verification.md` (importers excepted: their redirect walk is its own step). The payment dependency is met for every call outside `submit_order.php`, `includes/payments/` and `includes/fn/payments.php`; those wait on `payment-driver-layer.md`.
2. Done (PR #972): the named functions declared inside functions are closures (`render_sub_row` in `widget_14.php`, the five `convert_html_to_text()` callbacks in `includes/fn/mail.php`, the explorer's breadcrumb, folder table and icon helpers in `includes/panel/explorer.php`); `router_output_error()` computes its header and footer as values. Output unchanged (`tests/mail_html_to_text_test.php`). The PHPStan baseline went from 39 to 12 errors and holds no inner function, so a new one fails CI (`docs/memory/workflow/static-analysis.md`).
3. **"Who may write PHP" is implicit in four evals** (`get_page_content.php` page eval, `dregion`, `custom_php` markers, hooks). One `pg_php_regions_allowed_sources()` and a settings default of "designer code only" would make it explicit. Waits on the eval re-ordering parked in `legacy-page-designer-retirement.md`. M.
4. Done (PR #673): PHPStan level 0 and PHPCompatibility (PHP 7.1+) run in CI against baselines of today's findings (`docs/memory/workflow/static-analysis.md`). A PR that fixes an item here or in `php-compatibility-and-bugs.md` shrinks the baselines in the same PR.

## Where to start?
- Item 1b next (no wait: it leaves the payment files alone); the gateway calls after `payment-driver-layer.md` settles which of them move. Item 3 waits on the eval re-ordering.
