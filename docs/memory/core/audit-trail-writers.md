---
name: audit-trail-writers
description: Which save paths write audit_log rows, how they read the row before and after (pg_audit_fetch, pg_audit_record), which columns are left out, which paths are not hooked yet
---

## How does a save path write a line?
- `$before = pg_audit_fetch($table, $id)` before the write, `pg_audit_record($action, $table, $id, $before)` after it: the row after is read in the same request (none after a delete); a row found on neither side writes nothing. Type, id column and name column come from `pg_audit_tables()` (config → settings, user, products → product, page, orders → order, files → file, offers → offer, api_apps → api_app). Reads use the plain connection; a failed read stops nothing.
- Left out of both sides (`pg_audit_volatile()`): any column ending in `timestamp`, the last editor (`user`, `page_user`, `user_user`, `last_modified_user_id`), the derived SEO score (`seo_score`, `seo_analysis`, `seo_analysis_current`), `layout_modified`, `user.token`, `api_apps.last_used_ip`, config job bookkeeping (`last_sitemap_check_hash`, `software_update_available`, `mailchimp_sync_running`), `orders.mailchimp_sync_error`.
- Actions: `create`, `update`, `delete`, `recycle` (moved to the Recycle Bin, the row stays), `rotate` (API secret), `revoke` (device), `cancel` (order).
- Only the main table's row is compared: linked rows (a product's zones, images, groups, attributes; an order's ship_tos) are not. Offers are the exception: `_pg_offer_audit_state()` (`edit_offer_f.php`) is the editor's state with conditions and actions, used by the editor's save and toggle, `_pg_offer_delete()` (every caller) and the API update.
- PIN fields (`user_devpasspin`, `*_pin`) count as secrets since this step.

## Which paths write today?
- Settings: `includes/settings/screen.php` and `fragment.php` (the config row around the category's save module, label = category key); `smtp_settings.php` (the config.php defines, label `smtp`).
- API keys: `api_settings.php` (application create, update, rotate, delete; `api_device` revoke; `oauth_client` on/off and delete).
- Products: `add_product.php` (every created id, sets included), `edit_product.php`, `edit_products.php` (bulk edit, bin, delete), API `products.php` (create, update, bin, delete, bulk price).
- Pages: `add_page.php`, `edit_pages.php` (bulk edit, bin, delete), API `pages.php` (update, bin, delete).
- Orders: `delete_order()` (every caller), `edit_orders.php` (card data removal, bulk cancel), API `orders.php` (update, cancel).
- Files: `add_file.php`, `edit_file.php` (delete, rotate, WebP, SVG conversion, save, duplicate), `edit_files.php` (move, delete), API `files.php` (create, update, bin).
- Users: `add_user.php` (create).

## What is not hooked yet?
- Left for files other open work held: `edit_user.php`, `delete_users.php`, `product_builder.php` (only through its callers above), `edit_page.php`, `view_orders.php`, `view_order.php` (status, notes; its delete already goes through `delete_order()`), `view_folder_and_files_f.php` (explorer rename, move, delete; `pg_delete_page_record()`, `pg_recycle_page()`).
- Not attempted: designer publish (`pg_designer_save_page()`; page revisions already keep each publish), Workspace record changes (`includes/workspace/changes.php`), Recycle Bin restore and empty, imports (`import_users.php`, `import_products.php`), `update_order.php` shipping info, API webhooks, product duplication.

## Personal data?
- A deleted order or user keeps its whole row in `before_json` (card fields masked). The erasure wizard (plan step 4) has to decide whether audit_log rows are anonymised too.

## Tests?
- `tests/audit_log_test.php`: `pg_audit_volatile()`, every `pg_audit_tables()` type, labels, reads without a connection. The hooks themselves were not run against a database.

Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/privacy-data-pack-audit-log.md
