---
name: waf-country-restriction
description: Firewall country restriction (waf_handle_country(), 2026.4.10 step 10.2): place in waf_run(), allow/block, unknown country, exemptions, not an offence, save guard, sign-in screens kept to the panel.
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
- By switches, all on by default: `waf_country_exempt_api` (`integration.php`, `mcp.php`, `oauth.php`, `workspace_ical.php`, `feed.php`: callers present a credential and run wherever their provider does); `waf_country_exempt_panel` (`waf_country_is_panel_request()`: a script under the software directory, not `router.php`, not `get_file.php`); `waf_country_exempt_bots` (`waf_bot_verified()`, so Verify Search Engine Crawlers must be on).
- `api.php` counts as panel only with a staff session (`waf_country_staff_signed_in()`, role < 3): the store, cart and live chat call it too. Site pages and files are decided in the router phase, without a session. Deferring that decision on the session cookie was deliberately not done: a cookie is no proof and the file path never reaches init. Staff abroad who need the site go on the Allowed IP list.

## What does the save refuse (`includes/settings/firewall.save.php`)?
- Before anything on the screen is written: a code that is no country (`pg_sales_map_iso_countries()` + `T1`), `allow` with an empty list, and, while the panel is not exempt, settings that would refuse the request saving them (its country, unless its address is in the submitted allow list). Keep this lock-out guard.

## Why are the site's sign-in pages not exempt?
- Login, forgot password and set password pages (legacy page types or designed widget pages) are site pages: do not exempt them. Staff from a refused country, while the panel is exempt, stay on the panel's built-in forms: `waf_country_keeps_to_panel()` → `pg_sign_in_kept_to_panel()` / `pg_sign_in_screen_url()` (`includes/fn/forms.php`), used by `get_login_screen()`, `get_forgot_password_screen()`, `forgot_password.php` (the reset link opens `set_password.php`, whose GET draws the form) and the after-sign-in home page redirect.
- A new place that sends someone from the panel to a site sign-in page or home page must ask it too. It answers false whenever it cannot tell (no database, Monitor mode, restriction off, allowed address, panel not exempt).
- Tests: `tests/waf_country_test.php`.
