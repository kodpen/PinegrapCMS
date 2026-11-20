---
name: product-form-uploads
description: Plan (owner 2026-11-20, agent channel poll 160): a File Upload field in product forms, so a shopper's photo or print file stays with the cart line and the order; steps FU1 to FU6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 160, message 9994), asked whether a shopper may upload a file with a personalised product (a photo mug, a printed shirt, an invitation): "Evet, plana girsin". The option he chose: the field's settings choose the file types; the cart, the order, the order e-mail and the API show the file.

## What is there today?
- File Upload is offered and accepted on custom forms only: `select_field_type()` (`includes/fn/forms.php`), `pg_field_form_types()` and `pg_field_form_validate_type()` (`includes/forms/field_form.php`). A hand-made `add_field.php` post is refused too (`commerce/cart-product-form-file-field.md`).
- A product form is filled in the cart, never on the add-to-cart button: the designer cart and express order widgets (`widgets_cart.php`, `widgets_catalog.php` → `cart_action.php`), the classic cart (`get_shopping_cart.php` → `shopping_cart.php`) and classic express order (`get_express_order.php` → `express_order.php`). None posts multipart.
- `shopping_cart.php` and `express_order.php` delete all of a line's answers on every save and write them again; `cart_action.php` replaces field by field.
- No order reader looks at `form_data.file_id`, and the API's order `items` carry no answers at all.
- `get_file.php` opens a form attachment through its submission (`form_data.form_id`); a product answer has `form_id = 0`, and a private folder's own check runs first.
- Removing a cart line or deleting an order deletes the answers but not their files; only the privacy erase (`pg_privacy_erase_scope()`) collects a product answer's `file_id`.

## What is reused?
- The custom form's upload path (`platform/custom-form-upload-placement.md`): `pg_upload_file_refusal()`, `pg_upload_limits()`, `prepare_file_name()`, `pg_cf_place_upload()` and its roll-back, a `files` row with `attachment = 1` in the field's `upload_folder_id`.
- `form_fields.rules` (TEXT, JSON, NULL means no rule) for the allowed types and the size limit: no new column is foreseen.
- Object storage moves attachment rows like any other (`platform/file-object-storage.md`).

## What binds it?
- `plans/website-features-rules.md`: the files each step names, nothing changing on a running site by itself, a visitor's write, tests, looks. Nothing changes until someone adds a File Upload field to a product form.
- A shopper's upload: same-origin check, `waf_rate_exceeded()`, `pg_post_body_discarded()` right after `init.php`, names through `pg_basename()`. The field's allow-list comes on top of `pg_blocked_upload_extensions()`, never in its place.
- `get_file.php` calls no `functions.php` code and no `lang()` (`platform/router-no-functions-php.md`).

## What is built?
- Features: `product-form-uploads-features.md` (U0 to U15). Steps: `product-form-uploads-steps.md`. Claim as `[plan:product-form-uploads] <step> — <topic>`.

## Which questions are open for the architect?
- May `form_fields.rules` carry the upload rule, or is a column asked?
- Are a cart's files that never became an order swept by the general job, and after how many days? Nothing purges incomplete orders today.
- FU1 fixes running sites today (answers without a label): does it go first in this plan, or as a `bug` issue of its own?

## What is left out?
- Crop or edit in the cart, a proof sent back to the buyer for approval, a file on the add-to-cart button, uploads through the storefront API (its cart routes are not built). Each is a later yes.

## Whose look?
- The owner's: the field's settings (FU2), the designer cart with a file, an image and a refusal (FU3), the order view's file row (FU5a).
