---
name: erp-order-profit-deductions
description: Marketplace deductions on an order's profit (#10657): Trendyol settlements, one cost row per record (settlement:<id>), currency, the resumable read, what is skipped
---

## Where are an order's real marketplace deductions written?
- `includes/erp/order_profit_deductions.php`, run first by `erp_order_profit_job.php` (then the profit pass computes the marked orders in the same run). A connector that answers the `deductions` capability is read; Trendyol only (`mp_trendyol_deductions()`, facts in `commerce/trendyol-finance-facts.md`).
- One `erp_order_costs` row per finance record: kind and source `marketplace`, `source_ref` `settlement:<their record id>`, amount = the record's commission (sale and discount cancel +, return and seller discount −). A record read again updates its own row; nothing piles up.
- Any `marketplace` row on an order replaces the listing's commission estimate of that kind (`erp_order_profit_compute()`); the order turns `final` when nothing else is a rate.

## How is a record matched to an order?
- By `shipmentPackageId` = `marketplace_order_map.remote_package_id` of the same account (status `imported`); without it by `orderNumber` = `remote_order_id` only when one package of the account carries it.
- Only an order whose status is billable (`erp_order_billable_statuses()`) gets rows: the pass removes the rows of any other, so writing them would churn.

## How is the currency handled?
- Architect (2026-11-20, channel message 10292): the order's own currency and minor units. A marketplace order's unit is `orders.currency_code` (empty = base). Trendyol's records are lira (`TRENDYOLTR`); another affiliate's currency is unknown and the record is skipped.
- Another currency: `erp_order_profit_deduction_convert()`: the record day's rates from `erp_fx_rate_for()` (base per unit) as one factor, `erp_to_base()` rounds once with `round()`. No rate: skipped, the estimate stays.

## How is the read bounded and resumed?
- At most 10 requests an account a run (`ERP_ORDER_PROFIT_DEDUCTION_CALLS`), one settlement type a call, 14-day windows, 500 records a page. State `{from, type, page}` in `config_text` `erp_order_profit_deductions_<account id>`, saved only after the rows are written.
- First read: 30 days back. Caught up with now: the next run starts two days back (late records). Not caught up: the job comes back in a minute. A failed page: the account keeps its old state, the job logs one line; other accounts go on.

## What does it never do?
- Architect (2026-11-20, channel message 10189): no receipt, till, bank or account move, no invoice settled, no payout record; `otherfinancials` and `payment-order` (invoices, payouts) are never read. A test checks the file writes only `erp_order_costs`.
- Accounting off (`ERP_ENABLED`): reads and writes nothing, no call to Trendyol.
