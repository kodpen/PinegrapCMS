---
name: product-feed-streaming
description: How the daily product feed build streams a large catalog to data/feeds/ chunk by chunk (writer open/add/close, CSV/TSV work file), why, and the out-of-memory symptom it fixed (PR #1056)
---

## What went wrong before?
- `pg_product_feed_refresh()` collected every item of the catalog in one array, then each format's rows, then the whole body as one string. 12,000 products (variants count, each is a row) with 1,500-char descriptions needed 228 MB: at `memory_limit` 128M the daily job died with "Allowed memory size … exhausted" in `product_feeds.php`, wrote no status, and "Save and build now" showed an error page.

## How is a feed written now?
- `pg_product_feed_products($options, $each)` reads 500 products a query (`PG_PRODUCT_FEED_CHUNK`) and, with `$each`, hands each chunk's items to it instead of collecting them (no callback still collects).
- Refresh opens a writer per format that is on (`pg_product_feed_writer_open()`), feeds every chunk to all writers (`pg_product_feed_writer_add()`), then `pg_product_feed_writer_close()` moves the finished file over the old one in one rename. A catalog error passed to close outweighs the writer's own; on any error the work file is deleted and the old feed stays served.
- The same catalog: 32 MB peak, byte-identical files (md5 of all three formats compared).

## Why a work file for CSV and TSV?
- Their header names only the columns some row fills (`pg_product_feed_used_columns()`), known after the last row. The work file keeps every column of a row on one line (`pg_product_feed_spool_line()`: tab-joined, `\`, tab, CR, LF escaped; `pg_product_feed_spool_cells()` gives every byte back); close writes the header, then each line with the used columns. JSON Lines needs none: its work file is the feed.

## What stays in memory-only form?
- `pg_product_feed_render($format, $items, $options)` builds a body string from items (tests, small uses). The line helpers (`pg_product_feed_jsonl_line()`, `_csv_line()`, `_tsv_line()`) are shared, and `tests/product_feeds_test.php` checks that the streamed file equals the rendered body for every format, including a column only a later chunk fills, empty feeds and errors.
- `bytes` in the status is the file size the writer reports (`pg_product_feed_status()` falls back to the body length).
