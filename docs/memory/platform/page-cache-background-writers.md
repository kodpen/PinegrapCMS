---
name: page-cache-background-writers
description: How the content import, the translation job and the bulk move to the visual designer mark the page cache dirty outside a signed-in request
---

## Why do background writers mark the cache themselves?
- `pg_page_cache_request_dirties()` counts only a signed-in or API-client write request. A job run by cron or the general job's dispatcher has neither, so a writer that changes what a stored page shows calls `pg_page_cache_mark_dirty()` itself. Before this, a WooCommerce or Shopify import carried on by `job.php` added enabled products to cached category pages only after the TTL.

## Which ones do?
- Content imports: `pg_import_write()` marks the cache after a made item whose kind `pg_import_kind_dirties_pages()` lists (record, page, file, comment, product, redirect). Contacts and authors show on no page. A new writer kind must be sorted there (`tests/page_cache_background_writers_test.php`).
- Translations: `pg_tr_jobs_run_due()` (translation_job.php) marks it when a batch translated something.
- Bulk move to the visual designer: `pg_ltd_run()` marks it when a step ran, from the screen or `pg_ltd_job_tick()`.

## Which job writes still wait for the TTL?
- The ended pre-order flag (`pg_preorder_end_hourly()`): readers stop showing an ended pre-order on their own, so the stored page is already right.
- Product ratings recomputed daily (`pg_review_refresh_products()`).
