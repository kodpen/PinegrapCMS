---
name: accounting-sync-rules
description: Accounting sync rules: out only, the site's own app, one service at a time, the source record, one record per order, customers, items, currency, tax, refunds, only the job sends
module: accounting_sync
---

## What is the accounting sync?
- Owner (2026-11-27): QuickBooks and Xero sync, one of the global features (`workflow/decisions-made-product-117.md`); built as Xero (#12069 onward), then QuickBooks Online (AS6, AS7 #12211).
- Parts: `xero-connection.md`, `xero-tax-rates.md`, `quickbooks-connection.md`, `accounting-sync-queue.md` (table and job), `xero-sync-driver.md`, `quickbooks-sync-driver.md`, `accounting-sync-log.md` (a failed send); what each service asks: `xero-service-facts.md`, `quickbooks-service-facts.md`.
- The rules below were settled by the architect after the census (2026-11-27), beside the owner's table (`workflow/decisions-made-schema-54.md`).

## Which way does data go?
- Out only: sales, customers, items and payments go to the books; only ids, sync tokens and the answers to the site's own calls come back. No webhook and no change data capture from either service; nothing changed in the books reaches the shop or the ERP.

## Whose app, and how many services?
- Each site registers its own app (model A, as Paraşüt); a central app for every site is put off (`workflow/decisions-made-panel-14.md`).
- One accounting service per site: Xero and QuickBooks are never connected together, as two would book each sale twice (`pg_accounting_sync_target()`).
- Its own columns per service, as Paraşüt's `orders.parasut_*`, are not repeated: one table, `accounting_sync` (`commerce/marketplace-outbound-layer.md`).

## Which record is the source?
- As Paraşüt splits it: without the ERP module, the paid order and its `order_refunds` rows; with it, the ERP invoice, its receipts and its return invoices, so a sale never goes twice and invoices typed in the ERP go too.
- With the ERP on, an order without an ERP invoice is not sent and View Order says so.
- Only sales after the connection; earlier ones are not sent.

## How is a sale written?
- One record per order, never a daily summary.
- Each buyer is a customer: the pairing, else the e-mail, else made. Each product is an item not tracked as stock: the pairing, else its code, else made; stock stays on the site.
- Xero: an AUTHORISED invoice and a payment to the chosen clearing account; a refund a credit note paid out. QuickBooks: a SalesReceipt for a paid order, an Invoice and a Payment from the ERP; a refund a RefundReceipt or a CreditMemo.

## Which currency and tax?
- A sale goes in its own currency. When the books lack it the send fails with the service's reason; the site never turns multicurrency on there (neither service can turn it off).
- The site's tax is sent as it is: each local rate matched once to the service's tax code on its Connections page; a sale with an unmatched rate waits. A US QuickBooks company: TAX or NON and the site's total.

## How are refunds found?
- By reading `order_refunds` after a cursor, as GA4's refunds are (`ad-conversions-*`); no new event is announced.

## Where may the services be called?
- Never in a visitor's or panel request for a sale: a moment writes an `accounting_sync` row and only `job.php` sends. The Connections page calls the service only to connect and to read and save its accounts and tax codes.
- Screens follow the look (`ui/screen-composition-rules.md`).
