---
name: accounting-sync-census-source
description: QuickBooks and Xero sync census, source: the shop's order or the ERP invoice, with and without the ERP module, the moments and refunds each gives
---

## What does a site without the ERP module have?
- The order: `orders` has no currency column (the site's `BASE_CURRENCY_CODE`); lines carry `order_items.tax_rate` and `tax_total` (2026.4.4.php 2558); money is integer minor units.
- Moments: `pg_order_mark_paid()` (`includes/fn/ecommerce.php` 1182, stamps `orders.paid_at`, 2026.4.4.php 2517), `pg_order_complete()` (`includes/fn/order_completion.php` 868), `process_order_cancellation()` (`ecommerce.php` 12000); events go out through `pg_announce()` (`includes/fn/events.php` 36).
- Refunds: `order_refunds` rows (2026.1.14.php 32: order_id, amount_cents, refund_type, transaction_id, notes, created_at), written in three places: a return (`includes/fn/returns.php` 4270), a cancelled order refunded as store credit (`includes/fn/store_credit_refunds.php` 306) and Refund on the View Order screen (`view_order.php` 2906).
- Only the return announces an event (`order.return.refunded`, `returns.php` 4357); no `pg_announce()` sits by the other two. Ad conversions find GA4 refunds by reading `order_refunds` of the last 72 hours (`pg_ad_conversion_ga4_refunds_sql()`, `includes/fn/ad_conversions_ga4.php` ~395).

## What does a site with the ERP module have?
- `ERP_ENABLED` turns the module on; its tables are always installed. `erp_invoice_from_order()` (`includes/erp/order_bridge.php` 874, lines from `erp_order_lines()` 167) makes the invoice; `orders.erp_invoice_id` and `erp_account_id` (2026.4.4.php 2506-2510) link them.
- `pg_erp_auto_invoice()` (`ecommerce.php` 1254) makes it on its own when `config.erp_auto_invoice_on` is order_paid or order_shipped; off leaves it to a person.
- The ERP invoice carries what the order lacks: a customer card (`erp_accounts`), currency and rate (`erp_invoices.currency`, `exchange_rate`), VAT per rate group (`erp/erp-vat-per-rate-group.md`), the tax's name and a second tax (`erp/erp-tax-names-zones-and-second-tax.md`), return invoices, receipts (payments) and cancellation. Invoices typed in the ERP by hand have no order at all.
- Events through `erp_event()` (`includes/erp/events.php` 38), declared in `includes/erp/api.php` 133-164: `erp.invoice.created` (return invoices too), `erp.invoice.paid`, `erp.invoice.cancelled`, `erp.receipt.created`, `erp.receipt.cancelled`, `erp.account.created`.

## Which one is the source?
- Without the ERP: the order, when paid, and its `order_refunds` rows.
- With the ERP: the ERP invoice, its receipts and its return invoices, so that one sale never goes twice and hand-made invoices go too.
- Paraşüt splits the same way: `_parasut_order_documents_in_erp($erp_enabled, $provider_on)` (`includes/fn/parasut.php` 2174) sends documents from the ERP when it is on with an active e-document provider, otherwise from the order screen's buttons (`parasut_order_buttons_on()` 2187).
- Open: with the ERP on and automatic invoices off, an order without an ERP invoice would not be sent until a person makes one (owner question in `accounting-sync-census.md`).
