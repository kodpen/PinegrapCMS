---
name: invoice-order-shared-form
description: add_erp_invoice.php (Invoice an Order) draws its Order card, Summary and save bar through includes/forms/invoice_order_form.php (wide layout, step B11); what the screen keeps
---

## What does includes/forms/invoice_order_form.php hold?
- The Invoice an Order screen in the wide layout (`edit-screen-wide-layout.md`, step B11). Before, one full-width Order card held the picker, the "figures from the order" note and the New Invoice link, with the save bar under it.
- `pg_invoice_order_form_card($order_field)`: the form column, the Order card with the picker (liveform `output_field()` passed in, combobox) and its help line.
- `pg_invoice_order_form_status($counts, $manual_url)`: the sticky Summary: Orders to invoice, Awaiting payment (a warning badge when above 0), the note on what the invoice takes from the order, a New Invoice button to `add_erp_manual_invoice.php`.
- `pg_invoice_order_form_counts($open_orders)`: both counts over the picker's rows, awaiting payment by `pg_order_awaiting_payment()`. `pg_invoice_order_form_layout($columns, $can_create)`: the row and the sticky save bar; Create the Invoice is disabled while no order waits (before, a click answered "Order is required").
- Tests: `tests/invoice_order_form_test.php`.

## What stays in the screen?
- The gates (`validate_erp_access()`, the read-only refusal on arrival), the order query (500 newest billable orders), the option labels, the form tag and the POST through `erp_invoice_from_order()` with its redirects.
