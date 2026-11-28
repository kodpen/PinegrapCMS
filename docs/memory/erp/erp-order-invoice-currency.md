---
name: erp-order-invoice-currency
description: An order's ERP invoice in the currency the order was charged in, at the order's own rate (PR #12291): the header, the ledger, the gift card credit, the refusal when the rate is not known
---

## Which currency is an order's invoice in?
- The order's `charged_currency` at its `charged_rate` (`order-charged-currency.md` in `commerce/`): `erp_order_invoice_currency($order, erp_base_currency())` in `includes/erp/order_bridge.php`. An order in the home currency, or read before the columns, is home at rate 1, source `base`, dated the issue day, as before.
- Another currency: `erp_invoices.currency` that code, `exchange_rate` the order's rate, `exchange_rate_date` the order's day, `exchange_rate_source` `order`, `grand_total_base` = `erp_to_base(grand_total, rate)`. Lines and totals stay as the order charged them; nothing is converted.
- Written whether or not the ERP's foreign currency switch (`erp_fx_enabled()`) is on: the order's amounts are in that currency. The switch still decides the exchange difference posting.
- Since PR #12291 (2026-11); before it a marketplace order in USD was invoiced as the same figure in the home currency.

## Why six places?
- `charged_rate` keeps 8 decimals, the ERP's rate columns `DECIMAL(15,6)`. The rate is rounded once to six places with `round()`, so the header, the ledger row and every later settlement convert by the same figure. The invoice's base total may differ by a cent from the order's base in the sums (`pg_order_base_amount()`, eight places).

## What is refused?
- A charged currency whose rate is 0 (not known on the order's day, nor today, when the order was written) or rounds to 0: "The order was charged in {var:1} and its rate to the home currency is not known, so it cannot be invoiced.". Never today's rate: an amount charged is not worked out again.
- A total whose base value overflows (`erp_base_amount_fits()`): "The total is too large in the home currency.".
- Both before the transaction and the number.

## What about the gift card and the ledger?
- The invoice's ledger debit (`erp_account_post()`) carries the same currency, rate, date and source.
- `erp_settle_gift_card()` takes optional `currency`, `exchange_rate`, `exchange_rate_date`, `exchange_rate_source`: the gift card part of the order (in the order's currency) is credited and settled at the invoice's rate. `erp_unsettle_gift_card()` reverses a credit in its own currency and `amount_base`.
- A return of the invoice copies the parent's currency and rate already (`includes/erp/returns.php`).

## Tests?
- `tests/market_invoice_feed_test.php`.
