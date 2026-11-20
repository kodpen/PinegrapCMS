---
name: product-form-uploads
description: Plan (owner 2026-11-20, agent channel poll 160): a File Upload field in product forms, so a shopper's photo or print file stays with the cart line and the order; steps FU1 to FU6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 160, message 9994), on a shopper's file with a personalised product (photo mug, printed shirt): "Evet, plana girsin": the field's settings choose the types; cart, order, order e-mail and API show the file.

## What is there today?
- File Upload is offered and accepted on custom forms only: `select_field_type()` (`includes/fn/forms.php`), `pg_field_form_types()` and `pg_field_form_validate_type()` (`includes/forms/field_form.php`). A hand-made `add_field.php` post is refused too (`commerce/cart-product-form-file-field.md`).
- A product form is filled in the cart only: designer cart and express order widgets (→ `cart_action.php`), classic cart (→ `shopping_cart.php`), classic express order (→ `express_order.php`). None posts multipart.
- `shopping_cart.php` and `express_order.php` rewrite all of a line's answers on every save.
- No order reader looks at `form_data.file_id`, and the API's order `items` carry no answers at all.
- `get_file.php` opens a form attachment through its submission (`form_data.form_id`); a product answer has `form_id = 0`, and a private folder's own check runs first.
- Removing a cart line or deleting an order deletes the answers but not their files; only the privacy erase (`pg_privacy_erase_scope()`) collects a product answer's `file_id`. Nothing purges incomplete orders.

## What is reused?
- The custom form's upload path (`platform/custom-form-upload-placement.md`): `pg_upload_file_refusal()`, `pg_upload_limits()`, `prepare_file_name()`, `pg_cf_place_upload()` and its roll-back, a `files` row with `attachment = 1` in the field's `upload_folder_id`.
- `form_fields.rules` (TEXT NULL, JSON, written by `pg_cf_rules_json()`) for the allowed types and the size limit.

## What binds it?
- `plans/website-features-rules.md`. Nothing changes until someone adds a File Upload field to a product form.
- A shopper's upload: same-origin check, `waf_rate_exceeded()`, `pg_post_body_discarded()` right after `init.php`, names through `pg_basename()`. The field's allow-list comes on top of `pg_blocked_upload_extensions()`, never in its place.
- `get_file.php` calls no `functions.php` code and no `lang()` (`platform/router-no-functions-php.md`).
- Bug issue #10541 (the designer cart stores answers without their field name) lands before FU1; the plan builds on it.

## What did the architect decide? (2026-11-20, agent channel message 10011)
- The allowed types and size limit live in `form_fields.rules`, no column. If keeping the file on the cart line needs a column or table of its own, the step states it in its draft and asks in the agent channel before the rest is written; it is never folded into an existing text field.
- Deleting a shopper's files: the owner chose 30 days after the cart's last touch (poll 162, message 10010); an order's files are never swept. FU5 builds it.
- The blank labels are their own fix (#10541), not a step of this plan.
- `cart_action.php` and `includes/fn/order_completion.php` are held by other plans (referral-programme RF1 #10033, event-tickets TK1): an FU step entering them waits for whichever of those is open. Each step stays near 1,000 product lines.

## What is built?
- Features: `product-form-uploads-features.md` (U1 to U16). Steps: `product-form-uploads-steps.md`. Claim as `[plan:product-form-uploads] <step> — <topic>`.

## What is left out?
- Crop or edit in the cart, a proof sent back for approval, a file on the add-to-cart button, storefront API uploads. Each is a later yes.

## Whose look?
- The owner's: the field's settings (FU1), the designer cart with a file, an image and a refusal (FU2), the order view's file row (FU4a).
