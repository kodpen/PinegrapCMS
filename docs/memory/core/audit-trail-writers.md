---
name: audit-trail-writers
description: Which save paths write audit_log rows, how they read the row before and after (pg_audit_fetch, pg_audit_record), which columns are left out, which paths are not hooked yet
---

## How does a save path write a line?
- `$before = pg_audit_fetch($table, $id)` before the write, `pg_audit_record($action, $table, $id, $before)` after it (row after read in the same request, none after a delete; nothing on either side writes nothing). `WHERE id IN (…)`: `pg_audit_fetch_many()` + `pg_audit_record_many()`, one line per row. Types and name columns: `pg_audit_tables()`. A failed read stops nothing.
- After an INSERT, read the new id before recording: the line is itself an INSERT and changes `mysqli_insert_id()`.
- Left out (`pg_audit_volatile()`): columns ending in `timestamp`, the last editor, every `seo_*` column of page and products (scores recalculated after each save), `layout_modified`, `user.token`, `api_apps.last_used_ip`, config job bookkeeping, `orders.mailchimp_sync_error`.
- Secrets by name are `***` (PIN fields included), also inside JSON text: a value holding a JSON object or list is stored decoded and masked (`config.product_feeds` keeps feed tokens). Over-masked by name: `api_apps.require_signature`, `api_secret_prev_expires`, `api_secret_hint`.
- Actions: `create`, `update`, `delete`, `recycle`, `restore`, `rotate`, `revoke`, `cancel`, `refund`.
- Only the main row is compared, not linked rows (product zones, images, groups; an order's ship_tos). Offers: `_pg_offer_audit_state()` (conditions and actions included).

## Where is it written?
- Inside shared helpers, so every caller writes once: `pg_recycle_page()`, `pg_delete_page_record()`, `pg_delete_file_record()` (`view_folder_and_files_f.php`), `pg_pb_delete_product()`, `delete_order()`, `_pg_offer_delete()`, `duplicate_page()`, `duplicate_products()`. Recycle Bin emptying, its purge job and folder deletes go through them.
- Settings: `includes/settings/screen.php`, `fragment.php` (label = category), `smtp_settings.php`. API keys: `api_settings.php` (apps, devices, OAuth clients).
- Products: `add_product.php`, `edit_product.php`, `edit_products.php`, API `products.php`, `pg_pb_bin_variant_set()`, the file manager's catalog (name, bin, restore, on/off, bulk and quick edit).
- Pages: `add_page.php`, `edit_page.php`, `edit_pages.php`, API `pages.php`, file manager (rename, move, bin, restore).
- Orders: `edit_orders.php`, `view_order.php` (cancel, refund, marked paid, marked refunded, restore), API `orders.php`.
- Files: `add_file.php`, `edit_file.php`, `edit_files.php`, API `files.php`, file manager (create, upload, paste, folder copy, zip extract, rename, move, bin, restore, delete, WebP, save, rotate, bulk edit, design flag). Users: `add_user.php`.

## What is not hooked yet?
- Files other open work held: `edit_user.php`, `delete_users.php` (#672), `includes/workspace/changes.php` (#805), `view_orders.php` (export status), `pg_catalog_bin_product()` itself (`includes/fn/ecommerce.php`; its callers are hooked, the Workspace one is not).
- Not attempted: designer publish (page revisions keep it), imports, `update_order.php` / the order screen's Save (only ship_tos change), API webhooks.

## What did the sandbox show?
- 2026-10-20, MariaDB: settings, API apps, file manager (files and pages through bin, restore, delete from the bin), catalog, page editor and order cancel/restore each wrote one line with only the changed fields. A first save after an upgrade writes NULL → '' for new columns once. The page editor writes `page_home = ''` (not `no`) for a page that is not home.

## Personal data?
- A deleted order or user keeps its whole row in `before_json` (card fields masked); erasure (plan step 4) decides.

Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/privacy-data-pack-audit-log.md
