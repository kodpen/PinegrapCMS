---
name: waf-country-restriction
description: Firewall country restriction (waf_handle_country()): place in waf_run(), allow/block, unknown country, exemptions (panel sign-in for anyone, other panel scripts for staff only), save guard.
---

## What does it do?
- Settings › Firewall › Country Restriction. `config.waf_country_mode`: `off` (default), `allow` (only the listed countries get in), `block` (the listed ones are refused). `waf_country_list`: two-letter codes plus `T1` (Tor, only Cloudflare reports it), read by `waf_country_codes()`; the decision is `waf_country_refused()`.
- Runs only while the firewall is on; 403 in Block mode, `would-block` in Monitor. Log rule `geo-country`, category `country`, detail `<code> (<source>)` or `unknown`.
- Not an offence (no `waf_register_offence()`): the visitor attacked nothing, and counting it would ban them twice.

## Where does it run, and where does the country come from?
- In `waf_run()` after the bot classification, before the rate limits: `waf_handle_bot()` sets `waf_bot_verified()` for a proven crawler. Moved earlier, the verified search engine exemption dies. The address allow list returns before every rule, so an allowed address is never refused.
- Only from `pg_geo_country()`; never read `CF-IPCountry` yourself (an edge header counts only from a trusted peer): docs/memory/waf/waf-geo-country-lookup.md.
- An unknown country passes by default (`waf_country_unknown` = 0): a site with no location database and no edge header must not shut everyone out.

## What is exempt (`waf_country_request_exempt()`)?
- Always: `/robots.txt`, `/.well-known/` (ACME is checked from several countries), `health.php` (by the running script).
- By switches, all on by default: `waf_country_exempt_api` (`waf_country_api_scripts()`: `integration.php`, `mcp.php`, `oauth.php`, `workspace_ical.php`, `feed.php`, `marketplace_webhook.php`: callers present a credential and run wherever their provider does; not `shipworks.php` or `workspace_guest.php`, which are credential endpoints for the counters only); `waf_country_exempt_panel` (`waf_country_is_panel_request()`: a script under the software directory, not `router.php`, not `get_file.php`, and only a panel sign-in screen (`waf_country_panel_sign_in_scripts()`) or any script with staff signed in, `waf_country_panel_exempt()`); `waf_country_exempt_bots` (`waf_bot_verified()`, so Verify Search Engine Crawlers must be on).
- Which panel requests are exempt, and the sign-in screens: `waf-country-panel-exemption.md`.

## What does the save refuse (`includes/settings/firewall.save.php`)?
- Before anything on the screen is written: a code that is no country (`pg_sales_map_iso_countries()` + `T1`), `allow` with an empty list, and, while the panel is not exempt, settings that would refuse the request saving them (its country, unless its address is in the submitted allow list). Keep this lock-out guard.

## Where are the tests?
- Tests: `tests/waf_country_test.php`.
