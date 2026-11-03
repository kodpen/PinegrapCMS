---
name: custom-form-payment
description: Fee of a designed form (plan designer-form-logic FL4-FL6): the setting's shape, pg_form_fee() rules, where the setting and the paid state are kept
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
- Open: `custom_form_pages` has no JSON settings column and #5035 says "no schema"; the store of the setting and of the record's order link and paid state is asked in the decision issue linked from the FL4 PR. The order writer and `pg_form_payment_mark_paid()` follow the answer.

## Tested?
- `tests/custom_form_payment_test.php`: the fee from the product price and from a field, bounds, unreadable figures, the setting's shape.
