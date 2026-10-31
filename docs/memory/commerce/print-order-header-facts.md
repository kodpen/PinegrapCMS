---
name: print-order-header-facts
description: The printed order (print_order.php) carries the order screen's tax number, on-account, unpaid and cancelled state, order codes, installments and gift cards
---

## Which order screen facts does the printed order carry?
- Billing column: `VKN / TCKN` and `Tax Office` from the contact (`contacts.tax_number`, `tax_office`), each only with a value.
- Payment: an order with `erp_on_account = 1` reads "Put on account" (stored as `Offline Payment`) in the header and in the payment card.
- Status line: "(Awaiting bank transfer)", "(On account)" or "(Payment pending on PayPal)" while unpaid, through `pg_order_awaiting_payment()` / `pg_order_payment_pending()`, only when the row has `paid_at`; a cancelled order adds its reason and time. These are read right after the first fetch, before `$row` is reused.
- Header payment block (`$output_print_payment`): method, special offer code, reference code and currency print even without a payment method (a free order's offer code); currency only with multi-currency on and a stored code.
- Totals: "Number of Installments" above the installment charge.
- Applied gift cards: an "Applied Gift Cards" heading; the "Givex Auth #" row only for Givex cards (`givex != 0`).
- Recurring lines: `lang('No limit')`, not the literal `[no limit]`.
- The product form heading closes with `</div>`; a stray `</legend>` left the form answers inside the uppercase heading.

## What was left out on purpose?
- Repeating table headers on later printed pages (an enhancement, not a defect).

## How is it checked?
- `tests/print_order_parity_test.php` (source pins). Not rendered in a browser.

Related: docs/memory/commerce/print-order-shipping-card.md, docs/memory/commerce/order-view-screen-rows.md, docs/memory/ui/print-views-light-paper.md
