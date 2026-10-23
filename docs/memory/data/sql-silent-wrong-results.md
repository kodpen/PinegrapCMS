---
name: sql-silent-wrong-results
description: SQL that runs without an error but reads or writes the wrong thing: an INT or DATE compared to '', SET order, SELECT * replaced by a column list, columns a migration adds, table-specific traps.
---

## Which comparisons match the wrong rows?
- An `INT` column compared with `''`: MySQL turns `''` into 0 and every row that belongs to nothing matches (template rows, the other form type's rows). An id from a `LEFT JOIN` may be NULL or empty: check it before it goes into a query. Case: the receipt of a deleted product (`commerce/form-fields-zero-id-query-trap.md`).
- A `DATE` column compared with `''`: compare with `'0000-00-00'` (`date-column-empty-comparison.md`).
- `form_data.form_id` points at `forms.id`; `form_data.id` is one field row, never a form (`content-tables.md`).

## Which writes go wrong without an error?
- The assignments of a single-table `UPDATE ... SET` (and of `INSERT ... ON DUPLICATE KEY UPDATE`) run left to right, and a later one sees the NEW value of an earlier column: keep the order the code has (`designer/designer-collab-locking.md`: `session_key` last; `commerce/marketplace-listings.md`; `waf/waf-ban-ladder.md`).
- Sessions are non-strict, so an ENUM value out of range is stored as `''` and a too long string is cut (`orders-table-write-traps.md`, `files-type-column-width.md`).

## Why not replace a SELECT * with a column list?
- Some readers depend on columns added after they were written. `pg_pb_copy_row()` (`product_builder.php`) copies a row as `SELECT *` minus its identity, so a column added later is copied too. The OG image resolvers (`pg_og_image_from_file_*()`, `includes/fn/seo.php`) read `files` with `SELECT *` because `image_width`/`image_height` arrived in 2026.1.25 and naming them breaks the query on an older database. A hand-kept list silently stops copying the next column.

## What about a column a migration adds?
- Probe before reading (`pg_schema_has()`) behind a readiness function (pattern `pg_page_noindex_ready()`); when a step adds several columns the function asks for all of them (`release/schema-probe-rules.md`).

## Which table-specific traps are known?
- `products_images_xref` has NO `id` column: `ORDER BY id` is a failed query.
- Product forms: `commerce/form-fields-zero-id-query-trap.md`. IP address columns: `waf/waf-ipv6-ban-column-width.md`. Orders: `orders-table-write-traps.md`.

Related: docs/memory/data/db-helpers-and-error-contract.md
