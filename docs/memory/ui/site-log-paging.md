---
name: site-log-paging
description: The Site Log (view_log.php) database view paged and searched on the server; the error_log view stays printed whole; what is left of issue #2054
module: site_log_list
---

## Why was the Site Log slow, and what changed?
- The log keeps six months of events and the screen printed every row of the range on one page (2,038 rows, 1.3 MB of HTML on the dev site, issue #2054). The database view (the default, and the only one below the administrator) now draws the headings only; the rows come from `site_log_list_rows` (`includes/panel/lists.php`, gate role <= 2, token checked) one page at a time.
- Rows, sort and description cell live in `includes/fn/site_log_list.php`. The range goes in `data-pg-list-params` as `start_time` / `stop_time` (never `start`: DataTables posts its page offset as `start` and would overwrite it); the action takes whole numbers only (`pg_site_log_list_range()`).
- The "N records" badge counts the range (`pg_site_log_list_count()`).

## What stays printed whole?
- With "Show Error Logs" on (administrators), entries of the `error_log` files are merged with the database rows and sorted in PHP, and the type buttons filter in the browser; that view prints every row through the same row builder (`pg_site_log_list_row()`), with no `data-pg-list-action`.

## What does the search box find?
- Every word in the user, the description or the IP address. The type badge, relative time and "UNKNOWN" (no user) are not searched.

## What is left of #2054?
- The main menu panels' markup (`data-bs-content`, about 170 KB on every panel screen), the Repair Software permission scan (`pg_update_permissions_block()`, 3.4 s on each visit), the audit and WAF logs, `sitemap.xml` built on every request, and serving minified panel JS/CSS.
