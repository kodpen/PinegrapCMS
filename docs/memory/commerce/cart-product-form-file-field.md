---
name: cart-product-form-file-field
description: Where a shopper can upload a product form's File Upload file: the classic cart and classic express order since FU3; the designer cart widgets still post urlencoded until FU2
module: product_form_uploads
---

## Can a shopper upload a file in the cart?
- Classic cart (`get_shopping_cart.php` → `shopping_cart.php`) and classic express order (`get_express_order.php` → `express_order.php`): yes, since FU3 (PR #10642).
- Designer cart and express order widgets (`widgets_cart.php` → `cart_action.php`): not yet. Their form has no `enctype`, so only the file name arrives as text; FU2 adds it.

## How do the classic pages draw the field?
- `get_form_info()` (`includes/fn/content.php`), product form: the file input with `accept` from the field's rule (`pg_pfu_accept_attribute()`), and above it the kept file (`pg_pfu_kept_file_html()`): its name as a link, size, a preview for an image, a Remove box posted as `<field name>_remove`, and "Choose a new file to replace it:". A kept file drops the input's `required`.
- The system layout's cart form gets `enctype="multipart/form-data"` when a drawn product form holds a File Upload field (`$form_info['file_upload_exists']`).
- Custom layouts: `$attributes` gets the `enctype` when a line's form holds one; each File Upload field carries `files[quantity number]` (id, name, size, url, html; null when none) and `accept`. The layout draws its own `<input type="file">` named like any product field.

## What does a save do with it?
- Both saves call `pg_pfu_cart_reset()` instead of deleting all the line's answers: the File Upload answers stay, except those of units above the line's number of forms (their files go too).
- Each unit's File Upload field goes through `pg_pfu_cart_save_file()`: a new file is checked (`pg_pfu_file_refusal()`, then the rate limit), placed and kept (`pg_pfu_place()`, `pg_pfu_keep()`); a refused one is marked on its own field and the stored file stays; Remove deletes the stored file; no new file keeps it (`pg_pfu_save_action()`).
- Required: checked on Checkout (cart) and Purchase Now (express order) against the file held after the save (`pg_pfu_holds_file()`), not the posted value.
- Rate limit: 30 placed files per 10 minutes per address (`waf_rate_exceeded()`, scope `cart_upload`).
- A body over `post_max_size` stops at `pg_upload_refuse_discarded_body()` right after `init.php` (a 413 page with Go back), before the token check.
- A gateway return to `express_order.php` carries no files and no Remove box, so it keeps the stored files.

Pinned by `tests/product_form_uploads_cart_test.php`. Related: commerce/product-form-upload-field.md, plans/product-form-uploads.md
