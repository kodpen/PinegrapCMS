---
name: site-log-paging
description: The Site Log (view_log.php): database view paged and searched on the server, the error_log view printed whole, who a row without a user is, the language log lines are written in
module: site_log_list
---

## Why was the Site Log slow, and what changed?
- The log keeps six months of events and the screen printed every row of the range on one page (issue #2054). The database view (the default, and the only one below the administrator) now draws the headings only; the rows come from `site_log_list_rows` (`includes/panel/lists.php`, gate role <= 2, token checked) one page at a time.
- Rows, sort and description cell live in `includes/fn/site_log_list.php`. The range goes in `data-pg-list-params` as `start_time` / `stop_time` (never `start`: DataTables posts its page offset as `start` and would overwrite it); the action takes whole numbers only (`pg_site_log_list_range()`).
- The "N records" badge counts the range (`pg_site_log_list_count()`).

## What stays printed whole?
- With "Show Error Logs" on (administrators), entries of the `error_log` files are merged with the database rows and sorted in PHP, and the type buttons filter in the browser; that view prints every row through the same row builder (`pg_site_log_list_row()`), with no `data-pg-list-action`.

## What does the search box find?
- Every word in the user, the description or the IP address. The type badge, relative time and the label of a row without a user are not searched.

## Who is a row without a user?
- `pg_log_actor()` (`includes/fn/forms.php`), the one rule for the Site Log and the dashboard's Activity card: no user (or the literal `UNKNOWN` older writers stored) and no address reads "Scheduled job" (jobs run from the command line, so `log_activity()` stores no address); with an address "Unknown". A row with a user, `[SYSTEM]` error_log entries included, shows it as stored (#3642, issue #3332; before, the list printed a fixed English `UNKNOWN`).

## Which language is a log line written in?
- The software language, at the time it is written: the description is stored as text, so it is wrapped in `lang()` before `log_activity()` gets it, never passed as bare English (`core/lang-function-syntax.md`: values go in as `{var:N}`).
- A writer a visitor's request can reach (sign-in and its second step, passkeys, social sign-in, password, cart, cart quote, checkout and shipping rates, order cancel, photo gallery, comments, event reservations, return requests, comment watchers, one-click unsubscribe) uses `pg_log_lang()` with lang()'s array form: on a page drawn in another site language (`FRONTEND_LANGUAGE`) it holds `pg_tr_suspend()` so the line still takes the software language. Panel screens, the API, jobs and the command line use `lang()`. Why: `init.php` adopts a page's language from `pg_lang` for any form or AJAX call posted from it, so `lang()` there speaks the visitor's language (#3332, closed #3932).
- `get_file.php` (private file views, access denials, sign-in from the cookie) writes with `pg_log_lang()` since #3661; it loads `lang()` itself (`core/lang-loader.md`).
- A writer with no user passes the literal `'UNKNOWN'` (or `''`), never `lang('UNKNOWN')`: the translated word is not what `pg_log_actor()` recognises (#3661). `view_waf_log.php` still matches both for older rows.
- Lines name the record, not an id; a barcode delete logs only if a row went (`tests/site_log_named_lines_test.php`).
- `tests/site_log_language_test.php` lists the writers (moved over or checked), scans `includes/api` and fails on a bare literal or on `log_activity(lang(` in a visitor file. Only the `assets/lib/tiny_mce_3_5_10` advlink/advimage `add_file.php` pair stays bare English: `tools/check_lang.php` skips `assets/lib`, so a key there would never be checked.

## What is left of #2054?
- The main menu panels' markup (`data-bs-content`, about 170 KB on every panel screen), the Repair Software permission scan (`pg_update_permissions_block()`, 3.4 s on each visit), `sitemap.xml` built on every request, and serving minified panel JS/CSS.
