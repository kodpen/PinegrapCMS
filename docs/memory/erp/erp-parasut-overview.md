---
name: erp-parasut-overview
description: Paraşüt as a whole: connection card, the ERP's e-document driver, the order screen's legacy Create Invoice and E-Shipment, inbox, export; its decisions and which erp-parasut-* file holds each part
module: parasut
---

## What is it?
- Paraşüt is an outside accounting service; Pinegrap talks to its v4 API from two code paths sharing one connection:
  - the ERP's e-document driver `includes/erp/edoc/parasut.php` (`einvoice`, `earchive`, `accounts`): an ERP invoice becomes a sales invoice there, then e-Fatura or e-Arşiv;
  - the legacy client `includes/fn/parasut.php`: connection, token, product cards, the order screen's buttons, the inbox.
- The order screen's buttons work outside the ERP's books and give way once the ERP and an e-document provider are on (#639): one sale must never reach Paraşüt twice. Most refusals here stop a second invoice, e-document or stock move.

## Which decisions bind it?
- Owner (2026-10-21): VAT and withholding per rate group, as Paraşüt takes them (`erp-vat-per-rate-group.md`).
- Decided #2440-#2442, not built yet (`plans/erp-parasut-open-work.md`, `workflow/decisions-made-schema.md`): a Payment account pick posting the paid moment; a reused buyer card only gets its empty fields filled; a `sent` e-Fatura whose conversion failed keeps refusing Cancel.
- Live rounds and open items run only in the owner's local session. Never guess an endpoint (`erp-edoc-driver-layer.md`).

## What does a person see, and which file holds it?
- Settings › Accounting › E-Invoice, Paraşüt part: status line, "Connect in three steps", Advanced, Default Product/Service ID: `erp-parasut-connect-card.md`; grants, token, owner stamp, refresh: `erp-parasut-connection.md`; a 401 on a cached token: `erp-parasut-legacy-token-401.md`.
- ERP invoice: Send, Hand to GİB, Ask After the Status, PDF, Cancel: `erp-parasut-driver.md` (the path), `-driver-2.md` (e-Fatura or e-Arşiv, requests), `-invoice-lines.md` (totals check, kurus shift), `-send-failures.md` (no answer, failed job, deleted record), `-sent-cancel.md`.
- Buyer card: `erp-parasut-final-consumer-card.md` (11111111111); the linked card and ERP › Account sync: `erp-parasut-accounts.md`.
- An order shipped by E-Shipment: `erp-parasut-shipped-order.md`; invoiced by Create Invoice: `-legacy-invoiced-order.md`; kept ids deleted there: `-stale-kept-ids.md`.
- Product cards: `erp-parasut-product-card.md`, `-product-card-not-opened.md` (the default product), `-product-card-opening-stock.md`.
- Order screen "Create Invoice" and "E-Shipment": `erp-parasut-legacy-order-buttons.md` (when drawn), `-legacy-order-gates.md` and `-gates-limits.md` (statuses, the ERP's invoice, the "Sent" badge), `-legacy-invoice.md`, `-legacy-buyer-card.md`, `-legacy-buyer-number.md`, `-legacy-invoice-shipment.md` (both on one order), `-legacy-stale-ids.md`, `-legacy-press-claim.md` (one lock per order), `-legacy-shipment-stock.md`, `-legacy-shipment-deleted-products.md`.
- E-commerce › "Parasut E-Invoice Inbox": `erp-parasut-legacy-inbox.md`. Orders › Export for Paraşüt: `erp-parasut-legacy-export-text-cells.md`.

## What holds across the cluster?
- 10 requests per 10 s (`parasut_throttle()`); both paths retry once after a 429 and once after a 401; `erp_edoc_parasut_allow_time()` before a long read. Lists: 25 a page, follow `meta.total_pages`, at most `ERP_EDOC_PARASUT_FIND_PAGES`.
- Only a 404 `Record was not found` naming the record's type and id means deleted; 401 `No access to company` or `No route matches.` never clears a kept id.
- A create with no answer leaves the unsure mark `?`; the next try searches before writing.

## How is a new part added?
- New behaviour goes into the driver through `erp_edoc_parasut_request()`, never onto the legacy buttons, whose invoice and shipment code is to be removed (`plans/erp-edocument-leftovers.md`).
- A new Paraşüt id kept in a column joins the one resend (`parasut_stale_kept_action()`). Tests: those named in each file. The demo key expired 2026-10-25: write "unit tests only".
