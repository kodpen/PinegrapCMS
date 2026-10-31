---
name: audit-trail-writers
description: Which save paths write audit_log rows, how they read the row before and after (pg_audit_fetch, pg_audit_record), which paths are not hooked
---

## How does a save path write a line?
- `$before = pg_audit_fetch($table, $id)` before the write, `pg_audit_record($action, $table, $id, $before)` after it (row after read in the same request, none after a delete; no row either side, no line). `WHERE id IN (…)`: `pg_audit_fetch_many()` + `pg_audit_record_many()`, one line per row. Types, name columns: `pg_audit_tables()`. A failed read stops nothing.
- After an INSERT, read the new id before recording: the line is an INSERT too and changes `mysqli_insert_id()`.
- Actions: `create`, `update`, `delete`, `recycle`, `restore`, `rotate`, `revoke`, `cancel`, `refund`, `export`, `erase`.
- Left out, masked, JSON text: `core/audit-trail-stored-values.md`; content digest: `audit-trail-file-content.md`.
- Only the main row is compared, not linked rows (product zones, images, groups; an order's ship_tos). Offers: `_pg_offer_audit_state()` (+ conditions, actions).

## Where is it written?
- Inside shared helpers (callers add none): `pg_recycle_page()`, `pg_delete_page_record()`, `pg_delete_file_record()`, `pg_pb_delete_product()`, `delete_order()`, `_pg_offer_delete()`, `duplicate_page()`, `duplicate_products()`, `duplicate_product()`, `update_product_group_status()` (one `update` per product it switches); the Recycle Bin purge and folder deletes use them.
- Settings: `includes/settings/screen.php`, `fragment.php` (label: `audit-trail-settings-name.md`), `smtp_settings.php`. API keys: `api_settings.php` (apps, devices, OAuth clients).
- Products: `add_product.php`, `edit_product.php`, `edit_products.php`, API `products.php`, `pg_pb_bin_variant_set()`, the file manager's catalog (name, bin, restore, on/off, bulk and quick edit), `barcode_increase_inventory.php` / `barcode_decrease_inventory.php`, `find_and_replace.php`.
- Pages: `add_page.php`, `edit_page.php`, `edit_pages.php`, API `pages.php`, file manager (rename, move, bin, restore, bulk edit), `mass_edit.php` (grid and Find & Replace card: one line per changed page).
- Orders: `edit_orders.php`, `view_order.php` (cancel, refund, marked paid, marked refunded, restore), API `orders.php`, `view_orders.php` exports (`complete` → `exported` from the row the export read; one line per order the UPDATE changed).
- Files: `add_file.php`, `edit_file.php`, `edit_files.php`, API `files.php`, file manager (create, upload, paste, folder copy, zip create and extract, rename, move, bin, restore, delete, WebP, save, rotate, bulk edit, design flag); `create_file.php`, `*_design_file.php`, `*_theme_file.php` (system theme: after its size UPDATE; deactivated theme: own line), `edit_javascript.php`, `edit_theme_css.php`, `editor_edit_file.php`.
- Users: `add_user.php`, `edit_user.php` (save, contact link, delete), `delete_users.php` (row read before its tokens go).
- Contacts: `core/audit-trail-contacts.md`.
- Workspace (proposals, drawer, bulk, scheduled): `ws_change_write()`, `core/audit-trail-workspace.md`.
- Personal data, type `person`: `privacy_export.php` (`export`), `privacy_erase.php` (`erase`).

## What is not hooked yet?
- `pg_catalog_bin_product()` (`includes/fn/ecommerce.php`): its callers and `ws_change_write()` write the `recycle` line; its own would double them.
- Not attempted: designer publish (revisions keep it), imports, `update_order.php` / the order screen's Save (only ship_tos change), API webhooks, `edit_user.php` sign-in unlock, identity disconnect, two-step reset.

## What did the sandbox show (PR #905)?
- One line per request, changed fields only; a first save after an upgrade logs NULL → '' once; the page editor writes `page_home = ''` off the home page (not fixed).

## Personal data?
- A deleted order or user keeps its row in `before_json` (card fields masked) till erasure.

Related: docs/memory/core/audit-trail-stored-values.md, docs/memory/core/audit-trail-workspace.md, docs/memory/core/consent-log-and-audit-trail.md
