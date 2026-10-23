---
name: not-found-log
description: The 404 log (2026.11.0, 11.0.30, not_found_log): which 404s are recorded and which not, bots and verified crawlers, the end-of-request write, row cap and 90 days, the screen and its Create redirect
---

## What is recorded?
- `includes/fn/not_found_log.php`: one row per address that answered 404 after the `*` patterns. `pg_not_found_log_mark()` follows `pg_short_link_not_found()` in `output_error()`, `error()` (404) and `pg_sw_item_missing()`, once per request.
- Only the router's last hand-over to `get_page.php` counts (`PG_SHORT_LINK_PATH`): panel "not found" screens, `get_file.php`, feeds, the home page branch and the command line renders never reach it.
- The key is a short link name's stored form (`pg_not_found_log_name()`): no language prefix, no query string, at most 191 characters, only what `pg_short_link_name_fault()` accepts, no `*`, nothing under the software directory. Every row can become a short link; a scanner's `%`, `<`, quotes or long paths are not stored.
- Not recorded (`pg_not_found_log_decide()`): POST, the SEO pass, the search indexer, e-mail renders, a prefetch, a request the firewall logged (`waf_last_rule()`), and a 404 at a page that is there with no `/` in the name (a bad `?r=` or `?id=`: no redirect fixes it, and a short link with that name would hide the page).

## Who is counted?
- Bots are not, as for the visitor rows (`pg_request_is_automated()`). Search engine crawlers the firewall verified (`waf_bot_verified()`, firewall on with bot verification) are: `hit_count` counts every recorded request, `crawler_hits` those of them from such crawlers. A crawler is never counted on its user agent alone.
- Staff count; it records with visitor tracking off: no IP, no cookie, only scheme, host and path of the first referrer.

## How is it written, and how big can it get?
- At the end of the request (`register_shutdown_function()`), only when `http_response_code()` is 404, with raw `@mysqli_query`: a `db()` helper ends in `output_error()`, and an exit there stops the other shutdown functions. The schema probe runs at mark time.
- UPDATE first, INSERT ... ON DUPLICATE KEY for a new address: a plain ODKU takes an auto-increment value on every repeat (InnoDB), so ids would count requests. Here ids count rows, and an id under the cap means the table is under it.
- Row cap `PG_NOT_FOUND_LOG_MAX_ROWS` 10000, checked on 1 in 20 new addresses and by the daily sweep. It cuts by `last_seen` (rows not asked for the longest), not by `MAX(id)` as the firewall log does: the oldest rows here are the long-standing broken links. Retention 90 days on `last_seen`: the general job's daily sweep (`pg_not_found_log_sweep()`, state file `data/temp/not_found_log_sweep.json`), and every list reads `last_seen >= pg_not_found_log_since()`, so a site without cron shows the same. Constants, no config columns.
- Backups keep its rows (`data/backup-rowless-tables.md`).

## What does the screen do?
- `view_not_found_log.php`, managers and up (redirects are short links), in the Pages menu after Create Short Link and as a view switcher beside the short link list.
- Rows: most requested first, search, who asked (everyone, visitors, search engines), sort, first 1000. A row's state comes from `pg_not_found_log_answers()` (the router's name comparison, `pg_short_link_keys()`): a short link answers it now (edit it), one in the Recycle Bin holds the name, a page or file answers now, or Create redirect.
- Create redirect opens `add_short_link.php?name=…&redirect_code=301` (`pg_not_found_log_redirect_url()`): the name read as a pasted address, the answer set, the destination chosen by the operator, every check on save. Not a one-click write.
- Delete a row or clear the log: POST with the token.

Tests: `tests/not_found_log_test.php`, `tests/short_link_redirects_test.php` (`name_fault_codes`). Plan: `plans/seo-toolkit-redirects.md` step 5; the API read is step 8.
