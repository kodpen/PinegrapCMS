---
name: product-form-upload-field
description: The File Upload field of product forms: its rule in form_fields.rules, the check, place and keep functions, who opens the file in get_file.php, the one remove function
module: product_form_uploads
---

## Where is a File Upload field offered?
- On a custom form, a product form and a variant set's form template: `select_field_type()` (`includes/fn/forms.php`) and `pg_field_form_types()` (`includes/forms/field_form.php`). Shipping and billing forms never; Signature stays custom-only.

## Where are its settings kept?
- Folder in `form_fields.upload_folder_id` (Folder to upload File, required, edit access checked). Allowed types and largest size in `form_fields.rules` under the key `upload`: `{"images","pdf","extensions","max_mb"}`. No column (architect 2026-11-20).
- Field editor rows under the folder: Images (JPG, PNG, GIF, WebP), PDF, Other file types (typed extensions), Largest file size (MB). A new field starts with Images and PDF on.
- With no type chosen any file the blocklist lets through is taken. A typed extension `pg_upload_name_blocked()` refuses is refused on save, never stored. A size above `pg_upload_limits()['file_max']` is refused on save; empty means the server's limit.
- A variant set's template copies the whole row (`pg_pb_apply_form_template()`), so the rule goes to every variant.

## Which functions does the cart use? (`includes/fn/product_form_uploads.php`)
- Not in the `functions.php` manifest: every caller loads it with `require_once`.
- `pg_pfu_rule_clean()` reads the rule; `pg_pfu_file_refusal($upload, $rule, $file_max)` gives the message for an upload error, a blocked name, a type the field does not take or a file over `pg_pfu_size_cap()` ('' when fine or no file).
- `pg_pfu_place()` copies through `pg_cf_place_upload()` into a files row with `attachment = 1` in the field's folder; `pg_pfu_keep()` writes the unit's answer (data = file name, `form_data.file_id`) and deletes the file the answer held before.
- The designer cart's save calls them through `pg_pfu_cart_save()` (FU2, #10641), the classic cart and express order through `pg_pfu_cart_save_file()` (FU3, #10642); `commerce/cart-product-form-file-field.md`.

## Who opens the file?
- `get_file.php`, its own block before the folder's check: a file with `attachment = 1` held by a product answer (`form_id = 0`, `order_item_id > 0`; `pg_pfu_file_lines()`) opens for the session's cart (`$_SESSION['ecommerce']['order_id']`), the signed-in buyer (`orders.user_id`) and staff with role below 3 or the commerce right (`pg_pfu_access_verdict()`), whatever the folder allows.
- Anyone else: a visitor not signed in goes to sign in, a signed-in one gets the private file's 403. The submitted-form attachment check is skipped for these files.
- The functions get_file.php calls use only `db_items()` and PHP (`platform/router-no-functions-php.md`).

## What deletes the files?
- `pg_pfu_remove_line_files()` before the answers go in `remove_order_item()`, `pg_order_drop_saved_lines()` and `remove_item_from_cart.php`; `pg_pfu_remove_order_files()` in `delete_order()`; `pg_pfu_remove_unit_files($line, $quantity)` for a lowered quantity (called by FU2/FU3).
- A file another answer still holds is kept (`pg_pfu_files_to_remove()`). Pinned by `tests/product_form_uploads_test.php`.
- A cart that never became an order loses its files 30 days after its last touch: `commerce/product-form-upload-sweep.md`.

Related: commerce/cart-product-form-file-field.md, platform/custom-form-upload-placement.md, plans/product-form-uploads.md
