---
name: security-findings-2026-10-waf
description: Security findings of the 2026-10-20 firewall and helper scan: 1, 2, 3, 4, 5, 7 done; 6 (country rule) decided by the owner 2026-10-28; one PR each
---

## Where does this come from?
- Bug scan of `development` @ `60b0d27` (2026-10-20, #706), sandbox-checked (PHP 8.3, MariaDB 10.11, firewall in block mode, trusted proxy 127.0.0.1). Kept out of public issues (SECURITY.md). The API customers item is item 3 of `security-findings-2026-10-api.md`. Check `workflow/decisions-made.md` first; none is on it.

## What is still open? (one PR each)
1. Done (PR #952): addresses are compared packed (`waf_ip_packed()`, also in `waf_ip_matches_simple()`), `::ffff:a.b.c.d` as IPv4, and `waf_client_ip()` is normalised (`tests/waf_ip_match_test.php`).
2. Done (PR #2750): each Trusted Proxies entry names the header its proxy sets; an entry added or edited without one reads X-Forwarded-For only, a stored one keeps every vendor header until edited, and the ban shield reads the same headers (`waf/waf-trusted-proxy-headers.md`, `tests/waf_proxy_header_test.php`).
3. Done (PR #953, PR #1202): `waf_is_excluded()` and `waf_request_names_script()` match the resolved `SCRIPT_NAME`; the request path counts only for `router.php`, decoded, and not with a dot segment (`tests/waf_exclusion_test.php`).
4. Done (PR #2731): the global, sensitive, api.php and in-flight limits exempt only a crawler `waf_bot_verified()` proved (`waf_bot_rate_exempt()`); curl, an empty UA, a `bot` token and an unverified good-bot name count like a browser, and with bot verification off no bot is exempt (`waf/waf-bot-rate-exemption.md`, `tests/waf_bot_rate_test.php`).
5. Done (PR #951): `pg_db_run()` (`includes/fn/core.php`) logs the MySQL message; `output_error()` shows it only with DEBUG or during an install or update.
6. **Country rule skips the visitor scripts of the software directory** (low–medium; decided, owner 2026-10-28: only staff screens are exempt, as for api.php, the panel sign-in screens included; the visitor scripts follow the country rule). `waf_country_is_panel_request()` (~3857) counts `add_comment.php`, `custom_form.php`, `registration_entrance.php`, `express_order.php`… as panel; a refused country posts to them (sandbox 200, `/` 403). Staff-only, as for api.php, minus the panel sign-in screens?
7. Done (PR #950): `pg_safe_back_url()` (`includes/fn/output.php`) refuses `/\host`, `\\host`, a backslash in an absolute URL and control characters anywhere (`tests/back_url_test.php`).

## Where to start?
- The fixes 1, 2, 3, 4, 5 and 7 are done; 6 is decided (owner, 2026-10-28, asked by the architect) and may be claimed, tests in `tests/waf_*_test.php`.
