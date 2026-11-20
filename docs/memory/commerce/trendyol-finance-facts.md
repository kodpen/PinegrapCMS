---
name: trendyol-finance-facts
description: Trendyol finance (current account statement) service: settlements path, 15-day window, page sizes, record fields, the types read for an order's commission and their signs, what is not verified
---

## Where were these facts read?
- developers.trendyol.com, Türkiye pages read 2026-11-20: guide `v2.0/docs/current-account-statement-integration` (modified 2026-09-02) and reference `v2.0/reference/getsettlements` (modified 2026-02-16). No call made with real credentials; tests use fixtures shaped after the reference (`tests/fixtures/trendyol/settlements-*.json`).

## Which call is read?
- `GET /integration/finance/che/sellers/{sellerId}/settlements?transactionType=&startDate=&endDate=&page=&size=` on the same hosts and auth as every call (`trendyol-api-facts.md`). Dates are epoch ms; the range is at most 15 days. `size` is 500 or 1000 (default 500); `page` from 0. The answer is `{page, size, totalPages, totalElements, content[]}`.
- The guide also names `transactionTypes` (comma list, wins over `transactionType`) and a required `supplierId`; the reference names neither. The connector sends one `transactionType` and `supplierId` = the seller id.
- `otherfinancials` (DeductionInvoices, Stoppage, PaymentOrder …) and `payment-order` are invoices and payouts, not per order: never read (payouts are out of the plan).

## What does a settlement record carry?
- `id` (string), `transactionDate` (ms), `transactionType`, `barcode`, `debt`, `credit`, `commissionRate`, `commissionAmount`, `sellerRevenue` (decimals, no currency field), `orderNumber`, `shipmentPackageId`, `paymentOrderId`, `paymentDate`, `affiliate` (`TRENDYOLTR` or `TRENDYOLAZJV`).

## Which types make an order's deduction, with which sign?
- `mp_trendyol_finance_types()`: Sale +, Return −, Discount −, DiscountCancel +: the record's `commissionAmount` (taken without sign) times the sign, `round()`ed to minor units. Coupons, Trendyol's own discounts (TY*), provisions and manual commission corrections are not read: their direction in the guide's table contradicts itself.
- Amounts are lira for affiliate `TRENDYOLTR` (or none); a record of another affiliate has no known currency and is skipped.

## What is not verified?
- Whether `commissionAmount` is signed on a Return; the code takes its size and its sign from the type. The guide's sample has a commission larger than the credit, so its numbers are not taken as a rule.
- Whether `supplierId` is really required; how late a Sale record appears after delivery (the read goes back two days each time it catches up).
- The rate limit of the finance service (none written; the general 50 per 10 s is assumed).

Related: docs/memory/commerce/trendyol-api-facts.md, docs/memory/erp/erp-order-profit-record.md
