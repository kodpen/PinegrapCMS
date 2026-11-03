---
name: custom-form-payment-staff
description: Paid state of a designed form's record for staff and integrations: the badge, the order link, Record an offline payment, the API fields, form.paid
module: form_payments
---

## What do staff see? (#6367)
- `edit_submitted_form.php`, for a submitted-forms manager: Payment Paid / Unpaid badge (`pg_form_payment_record()`, `pg_form_payment_state()`: none, unpaid, paid); the order's link for role < 3 or `manage_ecommerce`; "Record an offline payment" when `pg_order_awaiting_payment()` holds for the order. It posts `record_offline_payment` to the same screen, which calls `pg_order_mark_paid()` with an audit line, so the order, the ERP auto invoice and the record move together.

## What do the API and webhooks say?
- Submissions (`api_form_submission_present()`, MCP reads the same schema): `order_number` (string, null until the order is completed or without a fee) and `paid` (boolean, null without a fee), read-only. The select joins `orders` only behind `pg_form_payment_ready()`.
- `form.paid` (`api_webhook_events()`): `pg_form_paid_event_payload()`, identifiers only as `form.submitted` (id, form_id, reference_code, contact_id, order_id, order_number, paid_at ISO UTC).


## Tested?
- `tests/custom_form_payment_test.php`: the state, the unpaid condition, the form.paid payload, the paid statement waiting for the order's paid_at. `tests/api_forms_test.php`: the two API fields.
