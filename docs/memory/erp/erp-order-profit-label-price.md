---
name: erp-order-profit-label-price
description: A carrier label's price as the order's shipping cost row (NP3): which drivers give it, how it is read and converted, its source_ref, cancel, Accounting off
module: carrier_labels
---

## Where does a label's price come from?
- `create()` of a driver may answer `price` = `{value, currency}`: the decimal as the service wrote it. `pg_label_answer()` keeps it only through `pg_label_price_normalize()` (`^\d{1,12}(\.\d{1,6})?$`, three-letter code); anything else is no price, never repaired.
- Kargonomi: the confirmed offer's price, Turkish lira before VAT ("24.10 + KDV"); with -1 (cheapest) the offer of the carrier the shipment names, else the cheapest priced one (`pg_label_kargonomi_price()`).
- Shippo: the bought rate's `amount_local`/`currency_local`, else `amount`/`currency`; a test transaction (`test: true`) has no price (`pg_label_shippo_price()`).
- Sendcloud: only when `create()` priced the options itself (no service set): the chosen option's `price.total`. A service set by the shop or the account, test mode (the free letter) and returns give no price.
- Other drivers give none; their orders keep the shipping rate.

## How is it written?
- `pg_shipment_label_open()` after the `shipment_labels` INSERT calls `pg_shipment_label_profit()` (`includes/fn/shipment_labels.php`), which loads `includes/erp/order_profit.php` only while `ERP_ENABLED`, and `erp_order_profit_label_record()` writes one `erp_order_costs` row: kind `shipping`, source `label`, `source_ref` `label:<shipment_labels.id>` (`erp_order_profit_label_ref()`), then `erp_order_profit_mark()`.
- Every label of an order is its own row, a return label too; they add up. Any label row replaces the order's shipping rate row (NP1's rule: a recorded row replaces the rate of its kind).
- Amount: `erp_order_profit_label_amount()`: the decimal at two places with `erp_decimal_units()` and `round()`; another currency than `BASE_CURRENCY_CODE` (the unit of `orders.total`) is converted with `erp_to_base()` at `pg_currency_rate()` of the label's day. No rate recorded: no row, the shipping rate stays.

## What happens on a cancel?
- `pg_shipment_label_cancel()` after marking the row cancelled calls `pg_shipment_label_profit()` with no price: `erp_order_profit_label_forget()` deletes that label's row and marks the order; the rate comes back when no label row is left.

## What is never done?
- Accounting off: nothing is loaded, read or written. No receipt, till, bank or account move (architect, channel message 10189).
- No schema change: the row lives in NP1's `erp_order_costs`.

## How was it verified?
- `tests/erp_order_profit_label_test.php` (amounts, conversion, rate replaced, off), the drivers' tests on recorded answers (`carrier_label_kargonomi_test.php`, `_shippo_`, `_sendcloud_`), `carrier_labels_test.php`. No real carrier was called; whether Sendcloud's `price.total` and Shippo's amount include VAT is not verified.
