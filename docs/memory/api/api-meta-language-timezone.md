---
name: api-meta-language-timezone
description: GET /meta language and site.timezone, GET /auth/me language: which language the API's texts come in (api_text_language()) and the zone the site's days are counted in (api_meta_timezone())
---

## What does `language` say?
- The language `error.message`, `user.role_name` and notification and inbox texts come in. `GET /meta` answers it at top level beside `software`; `GET /auth/me` answers it beside `kind`. No per-person language exists, so both are the same today; a per-person setting would change `/auth/me` first.
- One helper, `api_text_language()` (`includes/api/resources/meta.php`), over the pure `api_text_language_pick($frontend, $suspended, $enforced, $software, $has_file)`: `FRONTEND_LANGUAGE` unless `pg_tr_suspend()`, then `ENFORCEMENT_SOFTWARE_LANGUAGE`, then `SOFTWARE_LANGUAGE`, then `en`, the order `lang(array('info' => 1))` uses (`includes/fn/core.php`). Empty values fall through.
- A code without a language file (`pg_tr_ui_has_file()`) is answered `en`: `?pg_lang=de` on `integration.php` sets `FRONTEND_LANGUAGE`, but only `en` and `tr` have files and `lang()` keeps the English source text for `de`. So the field never names a language the texts are not in.

## What does `site.timezone` say?
- The IANA name PHP runs in once `init.php` has set it: `date_default_timezone_get()` through `api_meta_timezone()`, the site's `TIMEZONE` setting or the server's zone when the setting is empty. Never `''`; no offset, the name carries daylight saving.
- It is the site's zone, not the person's: `GET /auth/me` `user.timezone` is the profile zone, `''` when unset. Clients use the site zone for the day `GET /reports/sales` groups by, Workspace's `worked_on` limit and the week's Monday.

## Where is it declared and tested?
- `'language' => 'string'` in `api_meta_schema()` and `api_auth_me_schema()` (`includes/api/resources/account.php`), `site.timezone` in `api_meta_schema()`. No route row, scope or text changed.
- `tests/api_text_language_test.php`, `tests/api_meta_timezone_test.php`. Added by PR #2241 (issues #1642, #2075).
