---
name: erp-parasut-legacy-invoiced-order
description: ERP send of an order the legacy Create Invoice invoiced at Paraşüt: the unsure mark refuses unread naming the way out; a record id is read, only a deleted one frees the send (#1629, #1682)
---

## What went wrong? (#1629)
- The order screen's Create Invoice writes `orders.parasut_invoice_id` (a record id, or the unsure mark `?` after a create with no answer), never `erp_invoices`. Its buttons give way once the ERP and a provider are on (erp-parasut-legacy-order-buttons.md).
- `send_invoice` read only the order's number, date and shipment document, so such an order's ERP invoice went as a second `sales_invoices`: two invoices and e-documents at GİB for one sale, the receivable doubled, stock out twice.
- Every send goes through `erp_edoc_invoice_send()`; the automatic invoice sends none.

## What does the send do now?
- It reads `parasut_invoice_id` with the order row and asks `erp_edoc_parasut_legacy_invoice_check($value, $get, $invoice)` right after the type checks, before the invoice's own refusals and the buyer's card:
  - empty: goes on, nothing read.
  - the unsure mark (`parasut_order_invoice_unsure()`): refused, no request.
  - a record id: `GET sales_invoices/{id}`, judged by `parasut_stored_record_state()` (erp-parasut-legacy-stale-ids.md). 200: refused naming the record. 404 `Record was not found`, `SalesInvoice-<id>`: the id cleared (`_parasut_order_forget_record()`), the send goes on, the notice in every outcome. Anything else (no connection, no token, 401 `No access to company`, `No route matches.`, 5xx, 429): refused with Paraşüt's words.
- A refusal leaves the row `error` with no id; each Send reads again.

## What does a refusal tell the operator? (#1682)
- Unsure mark: no screen clears it while the provider is on, so the refusal names the way: e-Document Provider Off (E-Invoice settings), Create Invoice on the order screen (its search finishes the record it finds or writes one, erp-parasut-legacy-invoice.md), provider back on, send again. It goes only if the press left no record.
- 200, `net_total` = the ERP invoice's `grand_total` (or none read): keep the ERP invoice as a PDF; issue the record's e-document in Paraşüt if it never went through; or delete the record there first.
- 200, another total: the record Create Invoice keeps when Paraşüt would not delete it after its total check (`parasut_create_invoice()`): delete it there, never issue it. An order's invoice is `erp_invoice_from_order()`'s, whose total is the figure that check used. No amounts in the text: `erp_money_out_currency()` reads the currencies table.

## Why does the send not search for the record itself?
- The press's search matches what it wrote: the description (`lang('Order')` #number, in the pressing user's language), the order's buyer card and day. A send that missed it would clear the mark and invoice the sale twice at GİB.

## Why refuse a record Paraşüt holds rather than take it over?
- Its lines, totals and e-document are the legacy press's; the ERP row's own unsure-mark search takes over only the ERP's tries (erp-parasut-send-failures.md).

## Why is the reason not on the order card before a send?
- The card is provider-neutral; a record's state takes a request. After a refused send the card shows "Last answer: …" (`edoc_error`).

## What is still open?
- A cancelled legacy record answers 200 (`item_type` `cancelled`) and is refused; one with no e-document can be deleted after its cancel (2026-10-24).
- The reverse (ERP invoice sent, provider off, then Create Invoice) is refused since #1628 (erp-parasut-legacy-order-gates.md).
- İşbaşı's driver does not read the column.
- Nothing ran live (demo key expired 2026-10-25); the 404 and 401 bodies are Paraşüt's of 2026-10-24 (#1544).

## Tests
- `tests/erp_edoc_parasut_legacy_invoiced_test.php`: the check per answer and total, the way out's labels against the screens, the send refused on the mark before any request and its own checks, the source order.
