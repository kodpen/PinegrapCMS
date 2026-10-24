---
name: audit-trail-writers
description: Which save paths write audit_log rows, how they read the row before and after (pg_audit_fetch, pg_audit_record), which paths are not hooked
---

## How does a save path write a line?
- `$before = pg_audit_fetch($table, $id)` before the write, `pg_audit_record($action, $table, $id, $before)` after it (row after read in the same request, none after a delete; nothing on either side writes nothing). `WHERE id IN (…)`: `pg_audit_fetch_many()` + `pg_audit_record_many()`, one line per row. Types and name columns: `pg_audit_tables()`. A failed read stops nothing.
- After an INSERT, read the new id before recording: the line is itself an INSERT and changes `mysqli_insert_id()`.
- Left out, masked, JSON text: `core/audit-trail-stored-values.md`.
- Actions: `create`, `update`, `delete`, `recycle`, `restore`, `rotate`, `revoke`, `cancel`, `refund`, `export`.
- Only the main row is compared, not linked rows (product zones, images, groups; an order's ship_tos). Offers: `_pg_offer_audit_state()` (conditions and actions included).

## Where is it written?
- Inside shared helpers (a caller adds no line of its own): `pg_recycle_page()`, `pg_delete_page_record()`, `pg_delete_file_record()`, `pg_pb_delete_product()`, `delete_order()`, `_pg_offer_delete()`, `duplicate_page()`, `duplicate_products()`, `duplicate_product()`, `update_product_group_status()` (one `update` per product it switches); Recycle Bin emptying, its purge job and folder deletes go through them.
- Settings: `includes/settings/screen.php`, `fragment.php` (label = category), `smtp_settings.php`. API keys: `api_settings.php` (apps, devices, OAuth clients).
- Products: `add_product.php`, `edit_product.php`, `edit_products.php`, API `products.php`, `pg_pb_bin_variant_set()`, the file manager's catalog (name, bin, restore, on/off, bulk and quick edit).
- Pages: `add_page.php`, `edit_page.php`, `edit_pages.php`, API `pages.php`, file manager (rename, move, bin, restore, bulk edit).
- Orders: `edit_orders.php`, `view_order.php` (cancel, refund, marked paid, marked refunded, restore), API `orders.php`, `view_orders.php` exports (`complete` → `exported` from the row the export read; one line per order the UPDATE changed).
- Files: `add_file.php`, `edit_file.php`, `edit_files.php`, API `files.php`, file manager (create, upload, paste, folder copy, zip create and extract, rename, move, bin, restore, delete, WebP, save, rotate, bulk edit, design flag).
- Users: `add_user.php`, `edit_user.php` (save, contact link, delete), `delete_users.php` (row read before its tokens go).
- Workspace (proposals, drawer, bulk, scheduled): `ws_change_write()`, `core/audit-trail-workspace.md`.
- Personal data: `privacy_export.php` (`export`, type `person`; `core/personal-data-export.md`).
- Before #1237 bulk page edit, zip, product Copy, variant set on/off wrote nothing (#993).

## What is not hooked yet?
- `pg_catalog_bin_product()` itself (`includes/fn/ecommerce.php`): its callers write the `recycle` line, the Workspace in `ws_change_write()`; if it ever writes its own, theirs must go or they double.
- Not attempted: designer publish (page revisions keep it), imports, `update_order.php` / the order screen's Save (only ship_tos change), API webhooks, `edit_user.php` sign-in unlock, identity disconnect, two-step reset.

## What did the sandbox show (PR #905)?
- MariaDB, panel requests (settings, API apps, file manager, catalog, page editor, order cancel/restore): one line each, changed fields only. Pages with 35 KB and 1.1 MB trees deleted from the bin keep name and title. A first save after an upgrade writes NULL → '' once. The page editor writes `page_home = ''`, not `no`, off the home page (not fixed).

## Personal data?
- A deleted order or user keeps its whole row in `before_json` (card fields masked); erasure (plan step 4) decides.

Related: docs/memory/core/audit-trail-stored-values.md, docs/memory/core/audit-trail-workspace.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/privacy-data-pack-audit-log.md
