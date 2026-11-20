---
name: cart-product-form-file-field
description: A product form's File Upload field in the cart: the save paths (cart_action.php, shopping_cart.php, express_order.php), the kept file, refusals, the required check, how each cart draws it
module: product_form_uploads
---

## Where can a shopper upload a file?
- Designer cart widget: its update form (`widgets_cart.php`, `$update_form_html`) posts multipart to `cart_action.php` → `pg_pfu_cart_save()` (#10641).
- Classic cart (`get_shopping_cart.php` → `shopping_cart.php`), classic express order and the designer express order widget (both → `express_order.php`): `pg_pfu_cart_save_file()` (#10642).
- Every path names a field `order_item_<item>_quantity_number_<q>_form_field_<field>` and its Remove check `<name>_remove` (`pg_pfu_remove_name()`).

## What does a save do with a file field?
- A new file is checked (`pg_pfu_file_refusal()`, then `pg_pfu_upload_rate_exceeded()`: 60 files an hour per address, scope `pfu_upload`), placed and kept (`pg_pfu_place()`, `pg_pfu_keep()`). A new file beats the Remove check.
- No file sent keeps the stored one; Remove deletes it (`pg_pfu_remove_unit_answer()`). A refusal is `mark_error()` on the field's name, escaped; the other answers are kept.
- `cart_action.php`: a unit the line does not hold takes no file (`pg_pfu_unit_in_line()`); a lowered quantity calls `pg_pfu_drop_units_above()`.
- `shopping_cart.php`, `express_order.php`: `pg_pfu_cart_reset()` replaces the old delete of all the line's answers; File Upload answers stay except those of units above the line's number of forms (their files go too). Required: checked on Checkout / Purchase Now (the designer cart's checkout lands on the express order) against the file held after the save (`pg_pfu_holds_file()`).
- A body over post_max_size, right after `init.php` and before the token: `cart_action.php` sends the visitor back with `pg_upload_body_discarded_message()`; the classic pages stop at `pg_upload_refuse_discarded_body()` (a 413 page with Go back).
- A gateway return to `express_order.php` carries no files and no Remove check, so it keeps the stored files.

## How is a kept file shown?
- Designer widgets: `_pg_cart_item_form_blocks()` (`file`, `file_size`, `file_url`, `file_image_url`, `file_remove`, `control_required`), drawn by `_pg_render_cart_item_form_html()` and `pg_sw_part_cart_item_form()` (twin `_sdPartCartItemForm()`, binding `form_field_file_remove`). An answer with `file_id = 0` keeps no file.
- Classic pages: `get_form_info()` draws `pg_pfu_kept_file_html()` (link, size, image preview, Remove, "Choose a new file to replace it:") above the input, with `accept` from `pg_pfu_accept_attribute()`. The system layout's form posts multipart when a drawn product form has a file field; a custom layout gets the `enctype` in `$attributes` and, per File Upload field, `files[quantity number]` (id, name, size, url, html) and `accept`.
- A kept file drops the input's `required` attribute everywhere.

Pinned by `tests/product_form_uploads_cart_test.php` and `tests/product_form_uploads_test.php`. Related: commerce/product-form-uploads.md, commerce/product-form-upload-field.md
