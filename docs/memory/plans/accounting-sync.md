---
name: accounting-sync
description: Plan (owner 2026-11-27, poll 246): sales, customers and payments sent to Xero, then QuickBooks; the census is done, AS2 (Xero connected) is built, the sends use one new table (poll 250)
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): QuickBooks and Xero sync. The list it belongs to and its rules: `plans/global-features.md`.
- What the code has and what the two services ask: `plans/accounting-sync-census.md` and its parts (AS1).

## What did the architect settle after the census? (2026-11-27; not the owner's words)
- Out only. Xero first: it asks the site's owner only for a free app; QuickBooks' production keys ask Intuit's questionnaire.
- Each site registers its own app (model A, as Paraşüt; a central one is put off, `workflow/decisions-made-panel-14.md`). Xero and QuickBooks are never connected together.
- Source as Paraşüt splits it: the paid order and its `order_refunds` rows without the ERP module; the ERP invoice, its receipts and its return invoices with it. There an order without an ERP invoice is not sent and View Order says so.
- One record per order. Each buyer a customer (the pairing, else the e-mail, else made). Each product an item not tracked as stock (by its code, else made); stock stays on the site.
- Xero: an AUTHORISED invoice and a payment to the chosen clearing account; a refund is a credit note paid out. QuickBooks: a SalesReceipt for a paid order, an Invoice and a Payment from the ERP; a refund is a RefundReceipt or a CreditMemo.
- Only sales after the connection. A sale goes in its own currency; when the books lack it the send fails with that reason, and the site never turns multicurrency on there.
- The site's tax is sent as it is, each local rate matched once to the service's tax code; a sale with an unmatched rate waits. US QuickBooks: TAX or NON and the site's total.
- Refunds are found by reading `order_refunds`, as GA4's are; no new event.
- Owner (2026-11-27, poll 250, `workflow/decisions-made-schema-54.md`): one new table, `accounting_sync`, is the queue and the pairing.

## Which steps? (S under 400 lines, M under 1,000; each waits for the one before it)
- AS1 Census: done, `plans/accounting-sync-census.md`.
- AS2 Xero connected: done (#12069). Provider `includes/connect/providers/xero.php`; the organisation and the income, clearing and bank accounts sit in one sealed service entry, chosen on Xero's Connections page.
- AS3 (M) The queue and the pairing, the table `accounting_sync` by a migration step, module `includes/fn/accounting_sync.php`: a row written when an order is paid, a refund row is found or an ERP event fires; `job.php` sends through a driver, paced and retried, with the key that stops a second sale. No driver yet, so rows wait.
- AS3b (S) Tax rates matched under Connection details on Xero's Connections page, after Ledger accounts: Xero's TaxRates read, each rate of the site given one, kept by `pg_accounting_sync_pair_save()`.
- AS4 (M) Xero's driver: contact, item, invoice and payment, credit note, void, from the order and from the ERP, `Idempotency-Key`, its limits read from the answer's headers.
- AS5 (S) A failed send: the "Events" card on the service's Connections page with Try again, one line on View Order and on the ERP invoice, the daily operations alert; a connection marked `reauth` holds the queue.
- AS6 (M) QuickBooks connected: a provider may name return parameters that `pg_connect_finish()` keeps (`realmId`); addresses from the discovery document; the company read; accounts and tax codes chosen; its setup lines tell of the questionnaire.
- AS7 (M) QuickBooks' driver: the records above with `requestid` and `SyncToken`, its limits.
- AS8 (S) Close-out: the lasting rules to `api/`, the plan deleted.

## What does every step keep?
- Screens in the look (`ui/screen-composition-rules.md`); an old screen a step touches is brought over (`workflow/decisions-made-product-118.md`). No call to either service in a request: only `job.php` sends.
