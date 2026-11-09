---
name: erp-not-built-on-purpose
description: What the ERP deliberately does not do today (auto matching, e-waybill, order split, payment link, payout imports, bank API, legal income statement) and what the Paraşüt driver has not seen live.
---

## What does the ERP deliberately not do today?
These are phase limits, not bugs: do not report them as missing checks or build them on the side.
- No automatic settlement matching: FIFO is only a suggestion (`erp_settlement_suggest()`); a receipt closes an invoice only when somebody names it (erp-invoice-settlement.md).
- No e-Delivery Note (e-İrsaliye): `erp_waybills` is the internal waybill (erp-internal-waybill.md). The plumbing exists (`erp_edoc_waybill_send()`, the order card's `ewaybill` step, the file manager folder) but no driver implements `send_waybill`, so it reads "not available". Sending comes only with a provider that has a convert-to-e-document call (Paraşüt v4 `shipment_documents` has none).
- One invoice per order: `orders.erp_invoice_id` holds one; an order is never split over several invoices (the automatic invoice keeps this rule).
- No payment link on an invoice: the customer sees invoices, what is open and the statement in the "My Invoices" widget (`account_invoices`) and cannot pay from there; the storefront side only reads.
- No iyzico or marketplace settlement (payout) import: what a gateway or marketplace pays out is not reconciled automatically.
- No bank API: statements come as a file (CSV, XLSX, XLS, ODS) through the bank statement screen.
- The profit and loss is a management view, not an official income statement: no depreciation, no accruals (erp-profit-report.md).
- The offline (bank transfer) payment wait is a derived state, not a new order status value (erp-awaiting-transfer-orders.md).

## What has the Paraşüt driver not seen live yet?
- Live on a demo company (2026-10-19 to 21): the connection, contacts read and written (account sync), sales invoices and their totals, poll, delete (erp-parasut-driver.md, erp-parasut-accounts.md).
- Not yet: the conversion to e-Arşiv / e-Fatura, its trackable job, the e-document PDF and the e-Arşiv cancel: the demo company has no e-document integration. Ask Paraşüt support for a test e-Arşiv / e-Fatura integration on it, or use a company that has one.
- There is no sandbox: the staging host does not take merchant credentials, so tests run live on a demo company, with low-value one-line invoices and the cancel path tried on each. `client_id` / `client_secret` are not self-service: Paraşüt support (destek@parasut.com) issues them, with redirect `urn:ietf:wg:oauth:2.0:oob` unless another is registered with them.
- Export invoices go as e-Fatura (Paraşüt's documentation), but the driver sends only `SATIS`, `ISTISNA` and `TEVKIFAT` and refuses any other type.
- No queue worker processes `erp_edoc_queue`, and Paraşüt's API has no webhook: a document's state is read back only when somebody asks (erp-edoc-send-and-cancel.md). The worker's plan: `plans/erp-parasut-open-work.md`.
