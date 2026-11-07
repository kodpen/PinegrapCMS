---
name: waf-bug-fixes-2026-11
description: waf_run() judges a request without a Host header, rate buckets outlive the sign-in lockout, crawlers confirmed over IPv6, scan cap per source.
---

## Which runs does `waf_run()` skip as "not a web request"?
- Only those `waf_is_web_request($_SERVER, PHP_SAPI)` refuses: the command line, or no `REMOTE_ADDR` (a scheduled task under any SAPI). The Host header plays no part.
- Symptom: it also returned on an empty `HTTP_HOST`. An HTTP/1.0 request may omit Host and the web server answers it from its default site, so a banned or rate-limited client dropped the header and skipped the IP list, rate limits, bot policy and scan. No other code in `waf.php` reads `HTTP_HOST`. Test: `tests/waf_web_request_test.php`.

## How long does the shared rate sweep keep a bucket?
- `waf_rate_retention()` = `waf_rate_retention_seconds(login_throttle_lockout, login_throttle_minutes)`: the longest of an hour, the lockout and the failure window, in minutes clamped as `pg_login_throttle_limits()` does (lockout to 43200, window to 1440), read with `waf_setting()` (defaults on a database without the columns).
- Symptom: it raised the hour only when `pg_login_throttle_ready()` existed, which lives in `includes/fn/auth.php`. The router stage has not loaded it and is the stage whose `waf_sweep()` runs (its latch), so buckets older than an hour were deleted: a 1440-minute lockout ended after about an hour and a day-long failure window never filled. Test: `test_form_protection_retention_covers_sign_in_throttle` in `tests/form_protection_test.php`.

## How is a good bot's reverse DNS name forward-confirmed?
- `waf_verify_bot()`: PTR name (`gethostbyaddr()`), suffix check, then the name's A and AAAA records (`dns_get_record($host, DNS_A | DNS_AAAA)`); `waf_forward_confirm_match($ip, $records)` compares packed addresses (`waf_ip_packed()`), so notation does not matter. Without `dns_get_record()` an IPv4 visitor falls back to `gethostbynamel()` and an IPv6 one gets `unknown`, never `spoofed`. Verdicts cached 7 days in `waf_ip_reputation`.
- Symptom: `gethostbynamel()` is IPv4 only and the match was `in_array()` on text, so Googlebot over IPv6 was `unknown` (held to the global limit, refused by country restriction) or `spoofed` (403, offence, /64 ban) when the name also had an A record. Test: `tests/waf_bot_verify_test.php`.

## How many values does the signature scan inspect?
- `waf_collect_targets()` gives each of ARGS, BODY and COOKIE its own `WAF_MAX_VALUES` (250, keys counted), plus the request line, user agent and referrer. The 8192-byte value cut and depth 6 are unchanged.
- Symptom: one cap was shared in that order, so about 250 junk query parameters (`?aaa=1&...`, keys counted) left `$_POST` and `$_COOKIE` unscanned.
- A source over its cap adds no score: a large legitimate form can pass 250 values, and a score would need calibration (`waf-score-calibration-and-bot-order.md`). Test: `test_waf_collect_targets_budget_per_source` in `tests/waf_scan_targets_test.php`.
