---
name: cart-product-form-file-field
description: Why the cart's product form still has no working file upload path after FU1: the field exists but the cart forms post urlencoded and no cart save writes the file yet (FU2, FU3)
---

## Can a shopper upload a file in the cart yet?
- No. Since FU1 a product form can hold a File Upload field (`commerce/product-form-upload-field.md`), but no cart draws it as a working upload yet.
- The cart update form (`widgets_cart.php`, `$update_form_html`) has no `enctype="multipart/form-data"`, so a browser sends only the file name as a text value in `$_POST`; `$_FILES` stays empty.
- `cart_action.php`, `shopping_cart.php` and `express_order.php` rewrite a line's answers on every save and know no `file_id`.

## What is the file branch in the cart form renderer then?
- The `'file upload' => 'file'` kind in `_pg_cart_item_form_blocks()`, the `file` input in `_pg_render_cart_item_form_html()` and the cart list's `__field_file` token. They draw the input, but nothing stores what it sends.
- FU2 (designer cart) and FU3 (classic cart) add multipart, the upload write through `pg_pfu_file_refusal()`, `pg_pfu_place()` and `pg_pfu_keep()`, and keep the stored file when no new one comes.

Planned: `plans/product-form-uploads.md`.
