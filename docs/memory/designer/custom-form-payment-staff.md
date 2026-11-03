---
name: custom-form-payment-staff
description: Paid state of a designed form's record for staff and integrations: the badge, the order link, Record an offline payment, the API fields, form.paid
module: form_payments
---

## What do staff see? (#6367)
- `edit_submitted_form.php`, for a submitted-forms manager: Payment Paid / Unpaid badge (`pg_form_payment_record()`, `pg_form_payment_state()`: none, unpaid, paid); the order's link for role < 3 or `manage_ecommerce`; "Record an offline payment" when `pg_order_awaiting_payment()` holds for the order. It posts `record_offline_payment` to the same screen, which calls `pg_order_mark_paid()` with an audit line, so the order, the ERP auto invoice and the record move together.

## What does the submitted forms list show? (#PRNUM)
- Once `pg_form_payment_ready()`, `pg_submitted_form_list_source()` reads `forms.order_id`, `forms.paid_at` and adds the Payment column after Status (`pg_submitted_form_list_columns(true)`, kind badge, not sorted, so the kept sort codes stay): `pg_submitted_form_list_payment_badge()`, empty for a record without a fee order. The row adds the cell only when the record has `order_id`, so cells and columns always match.
- The toolbar's "Unpaid" toggle (shown only once the columns exist) sets the session filter `payment` = `unpaid` / `any`; `pg_submitted_form_list_payment_condition()` adds `pg_form_payment_unpaid_where('forms')` in the simple and the advanced view alike, so Export, Delete All and a whole-list selection follow it.

## What do the API and webhooks say?
- Submissions (`api_form_submission_present()`, MCP reads the same schema): `order_number` (string, null until the order is completed or without a fee) and `paid` (boolean, null without a fee), read-only. The select joins `orders` only behind `pg_form_payment_ready()`.
- `form.paid` (`api_webhook_events()`): `pg_form_paid_event_payload()`, identifiers only as `form.submitted` (id, form_id, reference_code, contact_id, order_id, order_number, paid_at ISO UTC).


## Tested?
- `tests/submitted_form_list_test.php`: the Payment column, badge and cell, the Unpaid condition.
- `tests/custom_form_payment_test.php`: the state, the unpaid condition, the form.paid payload, the paid statement waiting for the order's paid_at. `tests/api_forms_test.php`: the two API fields.
