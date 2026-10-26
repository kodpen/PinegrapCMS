---
name: catalog-group-writer
description: The one product group writer pg_catalog_group_create(): who calls it, the row, SEO score, product_group.created once, the API query helpers, screens that keep their own code
---

## What writes a new product group?
- `pg_catalog_group_create($data, $user, $opts)` in `includes/fn/ecommerce.php`: one browse row (variant selection on, no images, details or code), the products filed, the keyword clouds of the search results pages holding it rebuilt, its SEO score, `product_group.created`. Returns the id, 0 when no row was written.
- `$data`: name (as given; the caller trims), enabled, parent_id (0 or less: the top, `pg_catalog_group_top_id()`), short_description, full_description, keywords, title, meta_description, meta_keywords, product_ids. `$user`: an array with `id` or a user id, stamped in `user`.
- In steps: `pg_catalog_group_create_input()` settles the user id, the parent (the top read through a callable, only when none is named), the address source (short description, else name), the rich text with {path} (`prepare_rich_text_editor_content_for_input()`, path as a parameter) and the product ids, with no query; `prepare_catalog_item_address_name()` makes the address; `pg_catalog_group_write()` runs the INSERT ('insert' answers the id), then one link per product, through a runner callable bound to `pg_catalog_query()`.
- The caller checks first (name free, parent and products there) and writes its own Site Log line.

## Who calls it?
- POST /product-groups, `api_product_groups_create()`: 409 `name_taken`, parent and product checks, `api_dry_run_stop()` above the writer, handed `api_product_group_create_data()` (`description` as `full_description`) and `$app['owner']`, 500 when it returns 0, then its log line and 201.
- A Workspace create, `ws_change_write_product_group()` with `ws_change_group_create_data($values)` (the proposal's one parent, its products), through `ws_change_write()`.
- Not add_product_group.php (images, codes, details, its form) nor variant sets (`catalog-variant-set-writer.md`): own code (owner decision).
- Clouds: `pg_catalog_group_clouds($group_id, $clear_only, $opts)`; `api_product_group_clouds()` and `ws_change_group_clouds()` are one-line twins of it for updates and deletes.

## Why does it take 'api' => true?
- The API passes it: `pg_catalog_query()` then runs the statements through `api_exec()`, `api_value()`, `api_rows()`, `api_insert_id()`, which answer a failed query with the API's JSON 500; elsewhere the `db()` family (`output_error()`).

## Is a new group scored for SEO?
- Yes, every create: `pg_catalog_seo_rescore('product_group', $id)` after the clouds. It loads root `seo.php` when needed and runs `pg_seo_recalculate()` only once the SEO columns exist (`pg_seo_schema_ready()`).
- The same helper scores a Workspace product create (`ws_change_write_product()`, after `pg_pb_create_product()`) and stands behind `api_products_rescore()`. Before, neither was scored until a list screen or the nightly job.

## Who announces product_group.created?
- The writer, once per create, with `pg_announce()` ({id, name}). The API no longer queues it with `api_webhook_enqueue()` (no double); a Workspace create is announced too (owner decision). The event catalogue names both.
- add_product_group.php and the variant set builder still announce nothing.

## Which test covers it?
- `tests/catalog_group_writer_test.php` runs what needs no database: what `pg_catalog_group_create_input()` settles, the INSERT (`pg_catalog_group_insert_sql()`) from each caller's real mapping (`api_product_group_create_data()`, `ws_change_group_create_data()`) and the links held to the old statements, `pg_catalog_group_write()`'s statements in order (a recording runner), `pg_catalog_query()` with stand-ins for the API helpers and the db() path made to throw (`workflow/unit-test-database-exit.md`).
- From the source only: the thin wrappers (the API passes `$app['owner']`), the clouds twins, SEO and one announcement, the Workspace product score, the screens left alone.
