---
name: cart-product-form-answer-name
description: Designer cart product form answers keep their field name in form_data.name; readers fall back to form_fields.name for older rows (#10541)
module: forms
---

## What does the designer cart write for a product form answer?

`cart_action.php` builds the row with `pg_product_form_answer_row()` (`includes/fn/forms.php`) and inserts it with `db_q()`. `form_data.name` is the field's `form_fields.name`, as `shopping_cart.php` and `express_order.php` write it. Before #10541 the cart wrote `''`.

## What did the empty name break?

View Order and Print order (`get_submitted_product_form_content_without_form_fields()`) showed ": Ayşe"; the orders CSV export (`view_orders.php`) got an empty column heading; order completion (`pg_order_line_product_form_fields()`, `AND (name != '')`) skipped the rows, so `^^variables^^` came out empty.

## What about answers saved before the fix?

No data step. View Order / Print order and the CSV export select the name through `pg_form_data_name_sql()`: the stored name, or `form_fields.name` by `form_field_id` when it is empty, or `''` when the field is gone. Order completion (`includes/fn/order_completion.php`) does not use it yet: old rows still give empty `^^variables^^` there.

## Does the express order widget take this path?

No. Its form posts to `express_order.php`, which already writes the name.
