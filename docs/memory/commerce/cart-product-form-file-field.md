---
name: cart-product-form-file-field
description: Why the cart's product form has no file upload path: product forms cannot hold a file upload field and the cart form posts urlencoded; the file branch of the cart form renderer is unreachable
---

## Can a product form in the cart lose an uploaded file?
- No. A bug-hunt lead said `cart_action.php` writes `''` to `form_data` for a file field (its value only in `$_FILES`) and that `_pg_cart_item_form_blocks()` keeps `required` on a file field that already holds a file. Neither path is reachable.
- The field editor offers "File Upload" only when `form_type` is `custom` (`includes/fn/forms.php`, the `$output_file_upload_field_option` block); product and product group forms never get the type, and no API or product builder path writes it for a product form.
- The cart update form (`widgets_cart.php`, `$update_form_html`) has no `enctype="multipart/form-data"`, so a browser would send no file at all, only the file name as a text value in `$_POST`; `$_FILES` stays empty.

## What is the file branch in the cart form renderer then?
- The `'file upload' => 'file'` kind in `_pg_cart_item_form_blocks()`, the `file` input in `_pg_render_cart_item_form_html()` and the cart list's `__field_file` token only draw a field that a hand-made `add_field.php` post could create. They are kept as harmless; if product forms ever get uploads, the cart form needs multipart, an upload write (as `pg_submitted_form_check_field()` does for custom forms) and `cart_action.php` must keep the stored row when no new file comes.
