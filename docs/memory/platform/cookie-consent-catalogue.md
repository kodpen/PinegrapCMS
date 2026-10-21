---
name: cookie-consent-catalogue
description: Which cookies the cookie notice lists (pg_consent_catalogue()), the conditional rows, and what a new cookie needs
---

## Which cookies does the notice list?
- `pg_consent_catalogue($content)` (`includes/fn/consent.php`), category => rows of (name, written by the software, description):
  - necessary, always: the session cookie (`session_name()`), `software[auth]`, `software[remember_me]`, `software[device_type]`, `pg_consent`, `software[view_page_mode]` (written on every page for a signed-in user by `get_page.php` and `update_view_page_mode.php`, 10 years);
  - necessary, while `pg_lang_redirect_enabled()`: `pg_language` (`PG_LANGUAGE_COOKIE`, the language the redirect remembered, 1 year, HttpOnly);
  - analytics with `VISITOR_TRACKING`: `software[number_of_visits]`, `software[tracking_code]`, `lsid`; with Google Analytics `_ga, _ga_*`;
  - marketing with tracking and the affiliate program: `software[affiliate_code]`;
  - every script the page holds with `data-pg-consent` (`pg_consent_page_services()`), as a service of the site.
- Before PR #1090 (issue #768) `software[view_page_mode]` and `pg_language` were written but not listed.

## What does a new cookie need?
- A row here and its description in `pg_consent_ui_keys()` (the Translations screen's "Cookie window" group; `test_consent_ui_keys_match_the_notice` compares the list with the file's `lang()` keys) and in `tr.json`. An optional cookie is written with `pg_consent_setcookie()` and goes into `pg_consent_revoke_names()` too.
- Panel-only cookies (ERP profit choice `pg_erp_profit_orders`, the Workspace guest `pg_ws_guest`) are not on visitor pages and not listed.
- Test: `test_consent_catalogue_lists_the_view_mode_and_language_cookies` (`tests/consent_test.php`).
