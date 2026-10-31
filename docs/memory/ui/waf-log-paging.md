---
name: waf-log-paging
description: The firewall log's events (view_waf_log.php) paged, sorted and searched on the server; period, category and search filters stay in the address and drive the summary
module: waf_log_list
---

## What changed, and why?
- The screen printed the newest 500 `waf_log` rows of the period on every visit (697 KB of HTML on the dev site, issue #2054); older rows of the period could not be reached. Now the Recent Events card draws the headings only; the rows come from `waf_log_list_rows` (`includes/panel/lists.php`, gate role <= 2, token checked) one page at a time through `pgListServerOptions()`, every row of the period reachable.
- Filters, condition, sort, search and row live in `includes/fn/waf_log_list.php` (`tests/waf_log_list_test.php`). The summary cards use the same bound condition (`pg_waf_log_list_condition()`).

## What stays as it was?
- The period selector (24 hours, 7, 30 days; default 7), the category select and the search field (address, URL, rule, reference, user agent) stay in the address (`pg_waf_ban_log_url()` and the ban notice mail link to `?range=…&search=…`). The screen sends them nested as `{"filters": {...}}` (DataTables posts its own `search`); the action checks them again with `pg_waf_log_list_filters()`.
- The summary still counts requests with `SUM(hit_count)`; DataTables' counts are rows. Possible False Positives, Active Automatic Bans and the policy reports are untouched. An empty period still shows "No firewall events were recorded in this period." instead of a table.

## How is it sorted and searched?
- Heading links (`pg_list_state('view_waf_log')`): time (the last hit, newest first by default), hits, action, IP, rule, score; ties by `id` in the first level's direction. The sort menu sits in the card, outside every form.
- The search box: every word in the address, method, URL, user agent, rule, reference, target or matched text, or the shown action name.

## Where do Release, Allow, Clear Log and Clear Reports land?
- On the view they were sent from. `pg_waf_log_list_url($filters)` gives `view_waf_log.php` plus only the non-default filters (period other than 7, a category, a search); the four forms post to it and each handler redirects to it, so the filters come back through `$_GET`. The filters are read above the handlers for this.
- Symptom fixed: opening the log for one address (ban mail, Active Automatic Bans link) and pressing Release or Allow landed on the default 7-day, unsearched view. Pinned by `test_waf_log_list_url`.

## Why do Request and Match sit in an inner `<div>`?
- `table.chart td { white-space: nowrap }` (`backend.src.css`) stops every chart cell from wrapping, and a `td`'s `max-width` does not narrow an auto-layout column: a long URL and user agent stretched the table to 2263 px and pushed Match off the card (#4340). Each cell now holds a `div.text-break` with `white-space:normal` and `min-width`/`max-width` (Request 10–22rem, Match 8–16rem), so the text wraps and the table fits its card. Do not move the limit back onto the cell.
