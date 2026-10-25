---
name: catalog-group-writer
description: The one product group writer pg_catalog_group_create(): who calls it, the row, SEO score, product_group.created once, the API query helpers, screens that keep their own code
---

## What writes a new product group?
- `pg_catalog_group_create($data, $user, $opts)` in `includes/fn/ecommerce.php`: one browse row (variant selection on, no images, details or code), the products filed, the keyword clouds of the search results pages that gather it rebuilt, its SEO score, `product_group.created`. Returns the id, 0 when the row was not written.
- `$data`: name (as given; the caller trims), enabled, parent_id (0 or less: the top, `pg_catalog_group_top_id()`), short_description, full_description (prepared here with `prepare_rich_text_editor_content_for_input()`), keywords, title, meta_description, meta_keywords, product_ids. The address comes from the short description, else the name (`prepare_catalog_item_address_name()`). `$user`: an array with `id` or a user id, stamped in `user`.
- The caller checks first (name free, parent and products there) and writes its own Site Log line.

## Who calls it?
- POST /product-groups, `api_product_groups_create()`: 409 `name_taken`, parent and product checks, the same rehearsal answer, `api_dry_run_stop()` above the writer, 500 when it returns 0, then its log line and 201.
- A Workspace create, `ws_change_write_product_group()` with `ws_change_group_create_data($values)` (the proposal's one parent, its products), through `ws_change_write()` as always.
- Not add_product_group.php (images, codes, details, its form) nor `pg_pb_create_group()` (a 'select' variant set): they keep their own code (owner decision).
- Clouds: `pg_catalog_group_clouds($group_id, $clear_only, $opts)`; `api_product_group_clouds()` and `ws_change_group_clouds()` are one-line twins of it for updates and deletes.

## Why does it take 'api' => true?
- The API passes it: `pg_catalog_query()` then runs the statements through `api_exec()`, `api_value()`, `api_rows()`, which answer a failed query with the JSON 500 the API promises; elsewhere `db()` and its siblings (`output_error()`).
- The row is built by `pg_catalog_group_insert_sql()` (and `pg_catalog_group_xref_sql()`), apart from the run, so a test holds it to the statements both callers wrote before.

## Is a new group scored for SEO?
- Yes, every create: `pg_catalog_seo_rescore('product_group', $id)` after the clouds. It loads root `seo.php` when needed and runs `pg_seo_recalculate()` only once the SEO columns exist (`pg_seo_schema_ready()`).
- The same helper scores a Workspace product create (`ws_change_write_product()`, after `pg_pb_create_product()`) and stands behind `api_products_rescore()`. Before, neither a group create nor a Workspace product create was scored until a list screen or the nightly job.

## Who announces product_group.created?
- The writer, once per create, with `pg_announce()` ({id, name}). The API no longer calls `api_webhook_enqueue()` for it, so an API create is never announced twice; a Workspace create is announced too (owner decision). The event catalogue says "through the API or the Workspace".
- add_product_group.php and the variant set builder still announce nothing.

## Which test covers it?
- `tests/catalog_group_writer_test.php`: the INSERT and product links against the old API and Workspace statements, the thin wrappers, the clouds twins, SEO and one announcement, the Workspace product score, the screens left alone.
