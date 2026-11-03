---
name: custom-form-payment
description: Fee of a designed form (plan designer-form-logic FL4-FL6): the setting's shape and store, pg_form_fee() rules, the fee order, forms.order_id and paid_at
module: form_payments
---

## What is the fee?
- A one-line order of a fee product (any product; templates ship hidden ones), decided in #5035: no `form_payments` table. An unpaid record is saved, listed with an "Unpaid" badge, and gets no confirmation mail until paid.
- Module `includes/fn/form_payments.php`.

## What does the setting hold?
- `pg_form_fee_settings_clean()`: `product_id` (0 = no fee), `amount_source` `product` | `field`, `amount_field` (field name), `amount_min` / `amount_max` (minor units, 0 = no bound, swapped when reversed), `offline` 0/1. Unknown keys dropped.

## How is the amount worked out?
- `pg_form_fee($settings, $values)` (pure): `$settings` plus `product_price` read by the caller. null = no payment (no product, or the product priced 0). Field source: the posted figure through `pg_parse_amount()`, `round()` to minor units; a one-value list is its value. Under the minimum or over the maximum is refused (`below_min`, `above_max`), never moved to the bound; nothing positive is `no_amount`.

## Where are the setting and the paid state kept?
- Decided in #6129, step 11.0.158 (`upgrade_2026_11_0_form_fee()`): `custom_form_pages.payment` TEXT NULL holds the setting as JSON (`pg_form_fee_settings_encode()` / `_decode()`; '' or NULL = no fee); `forms.order_id` (indexed) points at the fee order, the order carries nothing; `forms.paid_at` is the completion time, 0 = unpaid. All behind `pg_form_payment_ready()`: code landed before the upgrade reads and writes no fee.
- `pg_cf_load_page_form_settings()` returns `payment` (the cleaned setting); `pg_cf_write_form_settings()` writes it when the save carries `payment`.

## How is the order written and marked paid?
- `pg_form_payment_order_write($record_id, $fee, $contact)`: an incomplete order (site currency, billing from the contact fields, `subtotal` = `total` = the fee, `offline_payment_allowed` only when the form and the store allow it), one `order_items` line of the fee product, quantity 1, no tax or shipping on top: the visitor pays the figure shown. Columns from `pg_form_payment_order_columns()` (pure). A record that already has an order keeps it, so a retry charges the same order. `orders.notes` untouched.
- `pg_form_payment_mark_paid($order_id)` is called by `pg_order_complete()` after the order number is given (every order) and by `pg_order_mark_paid()` (Payment Received). It marks only when the order itself is paid: `UPDATE forms INNER JOIN orders … WHERE forms.order_id = ? AND forms.paid_at = 0 AND orders.paid_at > 0` (#6367). An offline payment completes the order with `paid_at` 0, so its record stays unpaid until staff record the payment; before, completion marked it paid. A second message changes nothing; a record marked by the call is announced as `form.paid`.
- System fields `order_number` (the completed order's number) and `paid` ("Paid" or '') join `get_standard_fields_for_view()` once the columns exist.
- Staff screen, API and `form.paid`: `custom-form-payment-staff.md`.
- Not yet: the payment step on the site and the confirmation mail when paid (FL5).

## Where does the designer set it?
- Custom Form Settings, last rows (`_cfPaymentRows()`): the fee product (`list_fee_products`, the first 2000 by name), the amount source, the field and min/max in major units (stored in minor units), offline. Drawn only when `formMeta.payment` (store on and columns there). Written as one `payment` object (`.sd-cf-pay` handler).

## Tested?
- `tests/custom_form_payment_test.php`: the fee from the product price and from a field, bounds, unreadable figures, the setting's shape and its stored JSON, the order's columns, the once-only paid statement gated on the order's paid_at.
