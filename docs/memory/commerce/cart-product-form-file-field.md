---
name: cart-product-form-file-field
description: Where a shopper's File Upload works in the cart (FU2, #10641): the designer cart saves it through cart_action.php; the express order widget posts multipart, its save is express_order.php's (FU3)
module: product_form_uploads
---

## Can a shopper upload a file in the cart?
- In the designer cart widget, yes (FU2, #10641). Its update form (`widgets_cart.php`, `$update_form_html`) posts `multipart/form-data` to `cart_action.php`.
- The designer express order widget's form posts multipart too, but it posts to `express_order.php`, which stores no file until FU3.
- The classic cart (`get_shopping_cart.php` → `shopping_cart.php`) and classic express order: not yet (FU3).

## What does cart_action.php do with a file field?
- A field of type `file upload` skips the text upsert and goes to `pg_pfu_cart_save()` (`includes/fn/product_form_uploads.php`): check against the field's rule, place, keep on the unit's answer.
- No file sent (or the old non-multipart page) keeps the stored file. The `<control name>_remove` check (`pg_pfu_remove_name()`) removes it; a new file beats the check (`pg_pfu_cart_file_step()`).
- A unit the line does not hold takes no file (`pg_pfu_unit_in_line()`): 1..quantity for One Form per Quantity, else 1.
- A lowered quantity calls `pg_pfu_drop_units_above()`: the files and the file answers of the units above it go.
- Uploads count against `waf_rate_exceeded(…, 'pfu_upload', 60, 3600)` per file (`pg_pfu_upload_rate_limit()`).
- A refusal is `mark_error()` on the control's name (escaped with `h()`); the cart shows it under the field (`_pg_cart_item_form_mark_errors()`, `pg_liveform_snapshot::field_errors()`).
- A body PHP discarded (over post_max_size): right after `init.php`, before the token, the visitor goes back to the referrer with `pg_upload_body_discarded_message()`.

## How does the cart show a kept file?
- `_pg_cart_item_form_blocks()` gives an upload `file` (name), `file_size`, `file_url`, `file_image_url` (images only), `file_remove`, `control_required` and `error`. An answer with `file_id = 0` (a name saved as text before FU2) keeps no file.
- The old block (`_pg_render_cart_item_form_html()`, also the express order widget's) and the cart's designable part (`pg_sw_part_cart_item_form()`, twin `_sdPartCartItemForm()`) draw the same: preview, link, size, "Choose another file to replace it.", a Remove check (binding `form_field_file_remove`).
- A required upload with a kept file has no `required` attribute (`__field_control_required`); the star stays. The required check itself is the checkout's.

Related: commerce/product-form-upload-field.md, plans/product-form-uploads.md
