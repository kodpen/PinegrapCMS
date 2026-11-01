---
name: search-and-audits-causes
description: Research of 2026-11-01 behind plans/search-and-audits.md: why search index runs end early or remove pages and why the structure and accessibility passes stall, by file and line
---

## Why does an index run end early?
- No resume (`update_search_index.php`): one process; pages in id order (the query at 154 has no `ORDER BY`: in practice primary key order), designer records after every page (978), files (1227), the sweep (1369), `pg_cron_ran()` (1378). A runner that stops a site's `job.php` after a fixed time (a hosting job runner's limit, a Windows task's execution time limit) or a proxy in front of the panel window (Cloudflare: 100 s) cuts it, and the next run starts at the first page again.
- No isolation: `get_page_content()` at 226 (pages), 411 (events) and 814 (classic records) runs bare. A thrown `Error`, `output_error()` (any failed query through `pg_db_run()`), `go()`/`exit` or a fatal ends the run. Only designer records (1104) are isolated (#1197).
- Files on PHP 8: `shell_exec('which pdftotext')` (1269, 1276) is "Call to undefined function" where `disable_functions` names it; `convert_doc_to_text()` and `convert_ppt_to_text()` (1458, 1514) `fread()` a failed `fopen()` (a missing file: `TypeError`) or 0 bytes (`ValueError`).
- After a crash `pg_cron_dispatch_finished()` (`includes/fn/cron.php` 1305) stamps the job: System Status shows it healthy, the dispatcher waits its interval. `pg_cron_dispatch_output()` files the first 500 characters of the stripped progress page (title, inline script, the first items), never the error at its end. A fatal reaches `data/temp/php_errors.log` (Site Log screen); a failed query only the `error_log()` line in the server's log.
- A killed process runs no shutdown function: the lock stays (14400 s for the index), the heavy lane hands out nothing, and the never-stamped job is picked first again (`pg_cron_pick()`: smallest `last_run_at`, catalogue order on a tie, and the index is listed before the SEO, accessibility and backup jobs).

## Why do pages vanish from search?
- The sweep deletes `timestamp != $timestamp`, and the panel button and crontab take no lock. Of two runs, the first to finish deletes every row the other has already rewritten.
- A visual-editor record whose render fails loses its row by design (#1197).

## Why do the structure and accessibility passes stall?
- The accessibility cursor is written only when the budget runs out (`includes/fn/a11y.php` 799): a process killed first checks the same first pages again.
- Under the dispatcher a pass gets one 120 s tick a day (stamped as run): one pass of a large site takes days or weeks, and the link graph and orphan findings wait for the whole pass.
- `view_accessibility.php` (51) reads that stamp as "Last full check", and `pg_a11y_result_state()` then says "ok" for checks a partial pass never ran.

## Where does each pass render?
- Index: every page in site search (private folders and account and cart types too) and every record, anonymous (#4207). Structure: every page of a renderable type in any folder, the newest record of a record page. Accessibility: those in public folders. Both buttons render as the signed-in manager, the jobs anonymous. The same page is drawn up to three times a day by three code paths.
