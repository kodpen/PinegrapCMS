---
name: product-form-uploads-steps
description: Plan product-form-uploads, steps FU1 to FU6: the designer cart's labels, the field and the file's door, the designer carts, the classic carts, the order's readers and the API, close-out
---

## Which steps are there?
- FU1 (S) The designer cart's answer labels: `cart_action.php` only, test first (an answer saved there has its field name). Feature: U0.

- FU2 (M) after FU1: the field, its rules and the file's door, server side.
  - Existing files: `includes/fn/forms.php` (`select_field_type()`), `includes/forms/field_form.php`, `add_field.php`, `edit_field.php` (the settings rows), `get_file.php` (the product answer's check, placed before the folder's own), `includes/fn/ecommerce.php` (`remove_order_item()`, `pg_order_drop_saved_lines()`), `remove_item_from_cart.php`, `delete_order.php`.
  - New: `includes/fn/product_form_uploads.php` (the rule read from `form_fields.rules`, check, place, keep, remove), `tests/product_form_uploads_test.php`.
  - Tests, red first: the allow-list on top of the blocklist; the size cap; who opens the file (cart owner, buyer, order staff, a stranger); a removed line taking its file.
  - Pictures of the field's settings to the owner. Features: U1, U2, U8, U14.

- FU3 (M) after FU2: the designer cart and express order widgets.
  - Existing files: `widgets_cart.php` (multipart on both forms), `widgets_catalog.php` (`_pg_render_cart_item_form_html()`), `cart_action.php` (the upload, the kept file, `pg_post_body_discarded()`).
  - Pictures at 1440 and 390, light and dark: empty, an image, a PDF, a refusal. To the owner. Features: U3 to U7 for these widgets.

- FU4 (M) after FU2: the classic cart and classic express order.
  - Existing files: `get_shopping_cart.php`, `shopping_cart.php`, `get_express_order.php`, `express_order.php`, `includes/fn/content.php` (`get_form_info()`'s file input). Both saves keep a stored file when no new one comes.
  - Features: U3 to U7 for these pages.

- FU5a (M) after FU2: where people see the order.
  - Existing files: `view_order.php`, `includes/fn/content.php` (the two product form renderers and `get_form_review_info()`), `widgets_lists_order.php`, `widgets_order_preview.php`, `print_order.php`, `print_packing_slip.php`, `includes/fn/order_completion.php` (the plain receipt).
  - Pictures of the panel order view's file row to the owner. Features: U9, U10.

- FU5b (M) after FU2: the API, the CSV and completion.
  - Existing files: `includes/api/resources/orders.php`, `view_orders.php` (both CSV exports), `includes/fn/order_completion.php` (the submit-form copy), `includes/fn/privacy_export.php` if its list needs the name.
  - `php tools/check_api_schema.php`. Features: U11, U12, U13, U15.

- FU6 (S) close-out: every U number checked against the code with its PR; `commerce/product-form-uploads.md` written; `commerce/cart-product-form-file-field.md` rewritten for the built field; this plan's three files deleted.

## Which steps run side by side?
- After FU2: FU3 beside FU4 and FU5b. FU4 and FU5a both enter `includes/fn/content.php`, FU5a and FU5b both `includes/fn/order_completion.php`: never open together.
