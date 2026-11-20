---
name: product-form-uploads-steps
description: Plan product-form-uploads, steps FU1 to FU6: the field and the file's door, the designer carts, the classic carts, the order's readers and the API, the sweep, close-out
---

## Which steps are there?
- FU1 (M) after bug issue #10541 has landed: the field, its rules and the file's door, server side.
  - Existing files: `includes/fn/forms.php` (`select_field_type()`), `includes/forms/field_form.php`, `add_field.php`, `edit_field.php` (the settings rows), `get_file.php` (the product answer's check, placed before the folder's own), `includes/fn/ecommerce.php` (`remove_order_item()`, `pg_order_drop_saved_lines()`), `remove_item_from_cart.php`, `delete_order.php`.
  - New: `includes/fn/product_form_uploads.php` (the rule read from `form_fields.rules`, check, place, keep, remove), `tests/product_form_uploads_test.php`.
  - Tests, red first: the allow-list on top of the blocklist; the size cap; who opens the file (cart owner, buyer, order staff, a stranger); a removed line taking its file.
  - Features: U1, U2, U8, U14.

- FU2 (M) after FU1: the designer cart and express order widgets.
  - Existing files: `widgets_cart.php` (multipart on both forms), `widgets_catalog.php` (`_pg_render_cart_item_form_html()`), `cart_action.php` (the upload, the kept file, `pg_post_body_discarded()`). `cart_action.php` is held by RF1 and TK1: never open beside them.
  - Features: U3 to U7 for these widgets.

- FU3 (M) after FU1: the classic cart and classic express order.
  - Existing files: `get_shopping_cart.php`, `shopping_cart.php`, `get_express_order.php`, `express_order.php`, `includes/fn/content.php` (`get_form_info()`'s file input). Both saves keep a stored file when no new one comes.
  - Features: U3 to U7 for these pages.

- FU4a (M) after FU1: where people see the order.
  - Existing files: `view_order.php`, `includes/fn/content.php` (the two product form renderers and `get_form_review_info()`), `widgets_lists_order.php`, `widgets_order_preview.php`, `print_order.php`, `print_packing_slip.php`, `includes/fn/order_completion.php` (the plain receipt; held by RF1 and TK1).
  - Features: U9, U10.

- FU4b (M) after FU1: the API, the CSV and completion.
  - Existing files: `includes/api/resources/orders.php`, `view_orders.php` (both CSV exports), `includes/fn/order_completion.php` (the submit-form copy; held by RF1 and TK1), `includes/fn/privacy_export.php` if its list needs the name.
  - `php tools/check_api_schema.php`. Features: U11, U12, U13, U15.

- FU5 (S) after FU1: the sweep in the general job, in `includes/fn/product_form_uploads.php`; the job's file names the call. 30 days after the cart's last touch (owner, poll 162). Tests: an unordered cart past 30 days swept, one inside them kept, an order never swept. Feature: U16.

- FU6 (S) close-out: every U number checked against the code with its PR; `commerce/product-form-uploads.md` written; `commerce/cart-product-form-file-field.md` rewritten for the built field; this plan's three files deleted.

## Which steps run side by side?
- After FU1: FU2 beside FU3 and FU4b; FU5 beside any. FU3 and FU4a both enter `includes/fn/content.php`, FU4a and FU4b both `includes/fn/order_completion.php`: never open together.
