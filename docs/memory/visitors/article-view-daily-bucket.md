---
name: article-view-daily-bucket
description: Why per-view submitted_form_views INSERTs became a daily bucket table: schema, backfill cursor, cutover, retention, old table rename (2026.4.1).
---

## Why was the per-view `submitted_form_views` table replaced?
- Site with 336,000 requests/day spent 84% of wall time waiting (worse at low traffic, so not its own load).
- Table: 8M rows, 1.1 GB, 73% of the DB, 946 MB indexes, MyISAM. Every article view was one INSERT; a MyISAM INSERT locks the whole table while four B-trees update and none fits `key_buffer_size`. InnoDB buffer pool hit ratio was 84.3% (healthy 99.9%+).
- Two of four indexes were unusable: `submitted_form_id` (cardinality 28, left-most column of the composite, redundant) and `page_id` (6 values in 8M rows, never chosen).
- Readers: the classic "form view directory" page type (`get_form_view_directory_screen_content.php`, "most read in last N days") and designed form lists sorted by "Views in recent days" (`designer/record-view-counts.md`). The counter users see is another table (`submitted_form_info.number_of_views`, running total). Writers of both: the legacy record page (one block, easy to confuse) and `pg_sfv_count_view()` for designed record pages.

## What is the new schema?
- `submitted_form_view_stats`: `PRIMARY KEY (submitted_form_id, page_id, view_date)` (4+4+3 = 11 bytes).
- No sha1 `bucket_key` (unlike `visitor_content_hourly`, whose utf8mb4 varchar key exceeded 767 bytes): all three columns are fixed width.
- No secondary index: retention sweep and page/article delete paths scan, but only a few thousand rows. Paying index cost on every write to avoid that is the trade that ruined the old table.

## How does the backfill work without a PRIMARY KEY on the old table?
- All six `SHOW INDEX` rows are `Non_unique = 1`, so the id cursor of `pg_visitor_backfill_step()` is unusable. Cursor = `timestamp`, chunk = one calendar day (an indexed boundary and the bucket itself, so a chunk is one row per (article, page)).
- Use `strtotime('+1 day', $ts)`, NOT `+86400`: a day is not always 86,400 s and a DST boundary would split a date into two chunks.

## How is double counting avoided at cutover?
- `sfv_rollup_cutover` is fixed at upgrade time. Live writes go to the bucket from then; backfill handles only `timestamp < cutover`.
- The cutover day split in two sums correctly via `ON DUPLICATE KEY UPDATE views = views + VALUES(views)`; safe because the cursor never processes a chunk twice.

## How are retention and the old table handled?
- Sweep sampled 1/500 (old code 1/100), six months, from both writers (`pg_sfv_sweep_sampled()` on the designed path); the bucket table holds a few thousand rows, not millions. Designed lists cap their window at 180 days for this reason.
- Never sweep the old table while rollup is live: backfill reads it, and deleting rows the cursor has not reached silently loses history.
- After backfill: `RENAME TABLE ... TO submitted_form_views_old`, not `DROP`. No code writes to it so it takes no locks; derived data can be regenerated, deleting the source leaves no way back.

## What happens before the upgrade has run?
- `pg_sfv_stats_ready()`: an install with new code but un-upgraded DB keeps the old per-view INSERT path and the read query falls to the old branch. Same pattern as `waf_schema_ready()` and `pg_pb_form_template_ready()`.

## What is the known trade-off?
- `$summary_timestamp = time() - ($summary_days * 86400)` means exactly N x 24 h ago; the daily bucket rounds the edge to a calendar day, leaving a partial-day difference at one boundary. Irrelevant for "most read" ranking; an hourly bucket would fix it but make the table 24x larger.
