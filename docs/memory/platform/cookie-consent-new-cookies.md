---
name: cookie-consent-new-cookies
description: Adding a front-end cookie or third-party tag under the cookie notice: necessary or not, pg_consent_setcookie() plus a session copy, pg_consent_catalogue(), revoke names, pg_consent_hold_scripts().
---

## Does a new front-end cookie need consent?
- First ask whether it is strictly necessary: the session, signing in, the cart, a view the visitor picked (`software[device_type]`). Those are set with `setcookie()` and listed under `necessary`.
- Anything else (statistics, marketing) goes through `pg_consent_setcookie('analytics'|'marketing', $name, $value, $expires)` (`includes/fn/consent.php`): sent only when the visitor allowed that category, always while the notice is switched off (`pg_consent_allows()`). Keep the value in the session too, so the visit works without consent; only remembering it beyond the visit waits. Example: `software[tracking_code]` and `software[affiliate_code]` in `get_page.php`.

## Where must it be listed?
- In `pg_consent_catalogue()`, under its category with a one-line purpose: a cookie that is not there does not appear in the notice.
- If turning the category off again must delete it, also in `pg_consent_revoke_names()` (a trailing `*` matches a prefix, as in `_ga_*`); `pg_consent.js` deletes those names.

## How does a third-party tag the software prints wait for consent?
- `pg_consent_hold_scripts($html, $category)`: every `<script>` becomes `type="text/plain" data-pg-consent="<category>"`, its `src` kept as `data-pg-src`, and `pg_consent.js` starts it in place once the visitor agrees, no reload. Google Analytics in `get_page_content.php` is held this way. Never print such a tag as a plain `<script src>`.
- `data-pg-consent-builtin` keeps the software's own held tags out of `pg_consent_page_services()`, so their cookies are listed in the catalogue by hand (`_ga, _ga_*`).
- A tag the site adds itself is held by its author (`data-pg-consent`, named with `data-pg-consent-name`); the notice lists it from the page.

## Who writes the `pg_consent` cookie?
- Only `pg_consent.js` (value `1.a1.m0`, `SameSite=Lax`): `setcookie()` on PHP 7.1–7.2 cannot set `SameSite`. PHP only reads it, through `pg_consent_parse()` / `pg_consent_choices()`.

Related: docs/memory/platform/cookie-consent-notice.md
