---
name: product-form-uploads
description: A File Upload field in product forms: what the feature does from field editor to sweep, which PR built each part, the owner's and architect's decisions, what was left out
module: product_form_uploads
---

## What does it do?
- A product form, product group form or variant-set template can hold a File Upload field: a shopper's photo or print file stays with the cart line and then the order. Nothing changes until someone adds such a field.
- Owner (2026-11-20): the field's settings choose the allowed types; cart, order, order e-mail and API show the file (agent channel poll 160, message 9994).

## Which PR built which part?
- Field, rule, check, place, keep, `get_file.php` door, one remove function (U1, U2, U8, U14): #10598. `commerce/product-form-upload-field.md`.
- Designer cart and express order widgets (U3 to U7): #10641. Classic cart and classic express order (U3 to U7): #10642. `commerce/cart-product-form-file-field.md`.
- Order view, prints, previews, My Account, receipt e-mails (U9, U10): #10724. `commerce/product-form-upload-order-readers.md`.
- API answers, orders CSV, copy into a form created at completion, privacy unchanged (U11, U12, U13, U15): #10639. `commerce/product-form-upload-api-csv-completion.md`.
- Sweep of unordered carts in the general job (U16): #10638. `commerce/product-form-upload-sweep.md`.
- Code: `includes/fn/product_form_uploads.php` (`pg_pfu_*`, loaded with `require_once`, not in the `functions.php` manifest). Tests: `tests/product_form_uploads_test.php`, `..._cart_test.php`, `..._order_test.php`.

## What binds it?
- The field's allow-list comes on top of `pg_blocked_upload_extensions()`, never in its place; the size never above `pg_upload_limits()['file_max']`.
- Architect (2026-11-20): allowed types and size limit live in `form_fields.rules` (key `upload`), no column (agent channel message 10011).
- Owner (2026-11-20): a cart's files are deleted 30 days after its last touch; an order's files are never swept (poll 162, message 10010).
- A shopper's upload: the form's token, `pg_post_body_discarded()` right after `init.php` (before the token), a rate limit of 60 files an hour per address, names through `pg_basename()`.
- `get_file.php` calls no `functions.php` code and no `lang()` (`platform/router-no-functions-php.md`).
- A required file is checked where the checkout is asked for: `shopping_cart.php` and `express_order.php` (the designer cart's checkout lands on the express order).

## Who opens the file?
- The visitor whose cart holds the line, the signed-in buyer, and staff who manage orders, whatever the folder allows. E-mails carry the name only, never an address. The API gives the address, which opens for those people, not for the API key.

## What was left out?
- Crop or edit in the cart, a proof sent back for approval, a file on the add-to-cart button, storefront API uploads. Each needs its own yes from the owner.
- Signature stays custom-form only; shipping and billing forms take no File Upload.
- Updating an existing form at completion never sets a File Upload field.
