---
name: import-framework
description: Content import framework (2026.11.0, 11.0.156): imports/import_items tables, reader and writer contract, cursor file, run lock, dry run, mapping rows, report, 30-day undo, import.php
module: importers
---

## Where does an import keep its state?
- `imports`: one row per uploaded export (kind wordpress/woocommerce/shopify, file name, status uploaded → dry_run → running → done/failed → undone, mapping JSON, counts JSON per writer kind, `cursor` = items read, report JSON). `import_items`: every row a run made (kind, item id, source key); no column on the target tables (decision #5034).
- The uploaded file is `data/temp/import_<id>_source.<ext>`, the place of a running import `data/temp/import_<id>.json`, written after every item; a lost one restarts the reader and the keys written are skipped.
- Both tables come with the upgrade: everything asks `pg_import_ready()` first.

## What does a reader provide?
- `includes/import/<kind>.php`: `pg_import_<kind>_open($file)` (the first cursor, JSON-safe array), `pg_import_<kind>_next(&$cursor)` (next item or null), `pg_import_<kind>_plan($item)` (dry-run line: `title`, `detail`), optional `pg_import_<kind>_mapping()`.
- An item: `kind` (record, page, file, comment, contact, product, redirect), `source_key` (unique within the export; prefix it by site when two sites could clash), `title`, `data`.
- Mapping rows: name => `label`, `type` (`form`, `form_field` with `form` naming its form row, `folder`, `product_group`, `select` with its own `choices`), `required`. The screen fills the choices (forms and folders the user may edit, live product groups), `pg_import_mapping_clean()` keeps only offered values, and a field of another form is dropped.

## What does a writer provide?
- `includes/import/write_<kind>.php`: `pg_import_write_<kind>($item, $import)` → `result` made/skipped/failed, `id`, `reason`; `pg_import_unwrite_<kind>($item_id)` deletes through the kind's own path. `pg_import_writer_answer()` turns any other answer, or made without an id, into failed; a thrown error is failed with its message.
- A writer finds an earlier item (a comment's record) with `pg_import_item_id($related, $kind, $key)`.
- IM1 ships no writer: record, comment and author come with IM2, page and file with IM3, product with IM4, contact with IM5, redirect with IM6. A kind without a writer fails with "This site cannot write this kind of item yet."

## When is an item skipped?
- When its source key is in `import_items` of this import or an earlier one of the same kind that is not undone (`pg_import_related_ids()`, `idx_source`): a second upload makes nothing twice. Writers add their own checks (SKU, e-mail).

## Who works a running import?
- The screen's short requests (`ajax=1`, `step=run`, 8 s each, session closed first) and `job.php` every tick (`pg_import_job_tick()`, budget from `pg_cron_time_left(20)`), so an import carries on with the screen closed.
- `GET_LOCK('pg_import_<id>', 0)`: only one request works an import; a busy answer makes the screen wait 3 s.

## What does the report keep?
- Counts per writer kind; skipped and failed lines with reason up to 2000 (`PG_IMPORT_REPORT_LINES`), the rest counted as `more`. Made rows are not listed: `import_items` holds them.

## How does undo work?
- Open for 30 days after `finished_at` (`pg_import_undo_open()`), at once for a stopped run; a running one is stopped first. Rows are removed newest first, 8 s per request. A kind without an unwrite function keeps its rows and the run keeps its status, so Undo can finish later.
- The job deletes `import_items` of imports finished more than 30 days ago (5000 a tick) and their source file; the `imports` row stays as the report.

## How is the kind told?
- `pg_import_detect_kind()` (pure) from the first 8 KB: RSS with `wordpress.org/export/`; CSV header `handle` + `body (html)`, or name columns + `accepts (email) marketing` → shopify; `id`, `type`, `sku`, `regular price` → woocommerce. A kind with no reader file is refused on upload.

Tests: `tests/import_framework_test.php`.
