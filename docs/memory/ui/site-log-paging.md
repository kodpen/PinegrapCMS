---
name: site-log-paging
description: The Site Log (view_log.php) on the list table driver: database view in batches, the error_log view printed whole with a type filter, who a row without a user is, the language log lines are written in
module: site_log_list
---

## Why was the Site Log slow, and what changed?
- The screen printed every row of the range (six months kept, issue #2054). Since TD6 (#6386) the list is on the list table driver (`ui/list-table-driver.md`): `pg_site_log_list_source()` (gate role <= 2) prints the first 100 rows, the next come from `list_table_rows`. The old `site_log_list_rows` action is gone.
- Columns, rows, sort and description cell live in `includes/fn/site_log_list.php`; column keys are the `pg_list_state('view_log')` sort codes. The range is the source's `params_out` `start_time` / `stop_time`, whole numbers only (`pg_site_log_list_range()`).
- The "N records" badge counts the range (the first batch's total).

## What stays printed whole?
- With "Show Error Logs" on (administrators), entries of the `error_log` files are merged with the database rows and sorted in PHP; the screen sets the source's `all` and prints every row through `pg_site_log_list_row()`, so the browser sorts (the time cell's `data-pg-sort-value` is the Unix time) and searches it.
- The type buttons (All, Site Log, Error Log, Warning) are driver filters (`data-pg-list-filter="view_log"`, column `type`): the type cell carries `data-pg-filter` site/error/warn (`pg_site_log_list_type()`); before, `column(1).search()` matched hidden `pglog*` tokens.

## What does the search box find?
- Every word in the user, the description or the IP address; not the type badge, relative time or a row-without-user label.

## Who is a row without a user?
- `pg_log_actor()` (`includes/fn/forms.php`), shared with the dashboard's Activity card: no user (or literal `UNKNOWN`) and no address reads "Scheduled job", with an address "Unknown"; the literal `SYSTEM` of backups, alerts, recurring invoices and webhooks reads "System" (#5838). Other users, `[SYSTEM]` error_log entries included, show as stored (#3332).
- The first visit shows the "Last 30 Days" range (today and 29 days before), its button chosen (#5841). `.pg-log-msg` wraps anywhere and is at most 40rem, so the IP column stays in view (#5836).

## Which language is a log line written in?
- The software language, at the time it is written: the description is stored as text, so it is wrapped in `lang()` before `log_activity()` gets it, never passed as bare English (`core/lang-function-syntax.md`: values go in as `{var:N}`).
- A writer a visitor's request can reach (sign-in, passkeys, password, cart, checkout, order cancel, comments, reservations, returns, unsubscribe and the like) uses `pg_log_lang()` with lang()'s array form: on a page drawn in another site language (`FRONTEND_LANGUAGE`) it holds `pg_tr_suspend()` so the line still takes the software language. Panel screens, the API, jobs and the command line use `lang()`. Why: `init.php` adopts a page's language from `pg_lang` for any form or AJAX call posted from it, so `lang()` there speaks the visitor's language (#3332, closed #3932).
- `get_file.php` writes with `pg_log_lang()` (#3661) and loads `lang()` itself (`core/lang-loader.md`).
- A writer with no user passes the literal `'UNKNOWN'` (or `''`), never `lang('UNKNOWN')`: the translated word is not what `pg_log_actor()` recognises (#3661). `view_waf_log.php` still matches both for older rows.
- Lines name the record, not an id (`tests/site_log_named_lines_test.php`).
- `tests/site_log_language_test.php` lists the writers (moved over or checked), scans `includes/api` and fails on a bare literal or on `log_activity(lang(` in a visitor file. The `assets/lib/tiny_mce_3_5_10` `add_file.php` pair stays bare English (`check_lang.php` skips `assets/lib`).

## What is left of #2054?
- The main menu panels' markup (about 170 KB per panel screen).
