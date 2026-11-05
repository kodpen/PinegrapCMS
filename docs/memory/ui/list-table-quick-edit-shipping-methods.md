---
name: list-table-quick-edit-shipping-methods
description: All Shipping Methods list quick edit (shipping_method_list_edit.php): the fields, service and real-time rate, cells a pickup method or variable base rate keeps as text
module: shipping_method_list_edit
---

## Which cells change?
- Every list value `edit_shipping_method.php` changes (`pg_shipping_method_edit_columns()`): Name, Message, Code, Service, Real-Time Rate, Base Rate, the two weight rates, Item Rate, Handling, Transit, Street, PO Box, Protected. Text: the zones (several choices) and Last Modified. `tests/shipping_method_list_edit_test.php` fails for a new column that is neither.
- Before, only the base rate and the item rate were fields (the functions sat in `includes/fn/ecommerce.php`).

## What does Service offer, and how does the real-time rate follow it?
- The services of the carriers set up (`pg_shipping_method_quick_ready()`, the screen's picker rule), then any other service a method keeps; the write takes only what the row's own picker would offer (`pg_shipping_method_service_choices()`).
- A blank or FedEx service turns the real-time rate off, as the screen saves it; the answer redraws that cell. A real-time rate without a USPS or UPS service is refused.

## Which cells stay text on some rows?
- A pickup method (`stock_location_id` > 0): Service, Real-Time Rate, Base Rate, Street, PO Box, which the screen forces (`pg_stock_pickup_columns()`). A variable base rate: Base Rate (a step per subtotal). `pg_shipping_method_quick_locked()` decides both for the cell and the write.

## Who may change them, and how is a change written?
- The screen's rights (`validate_ecommerce_access()`); `view_shipping_methods.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.
- `pg_shipping_method_quick_change()` (pure) applies the form's checks (`pg_shipping_method_form_amount_valid()`, `pg_shipping_method_form_days_valid()`, the required name); `pg_shipping_method_quick_update()` writes only the changed columns with the user and time, logs the change and announces `shipping_method.updated` like the screen's save. The answer is the row read again (`pg_shipping_method_list_sql($id)`, `pg_shipping_method_list_cells()`): every cell with its field's value.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
