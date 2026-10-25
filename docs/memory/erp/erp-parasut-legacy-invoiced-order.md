---
name: erp-parasut-legacy-invoiced-order
description: The ERP send of an order the legacy Create Invoice already invoiced at Paraşüt: the unsure mark refuses unread, a record id is read first, and only a deleted record lets the send go on (#PR)
---

## What went wrong? (#PR)
- The order screen's Create Invoice writes `orders.parasut_invoice_id` (a record id, or the unsure mark `?` after a create with no answer), never `erp_invoices`. Its buttons give way once the ERP and a provider are on (erp-parasut-legacy-order-buttons.md); the ERP's order card then offers its invoice and e-document.
- `send_invoice` read only the order's number, date and shipment document, so such an order's ERP invoice went as a second `sales_invoices` and was converted: two invoices and e-documents at GİB for one sale, the receivable doubled, stock out twice when no shipment document was named.
- Each e-document send goes through `erp_edoc_invoice_send()`: the order card's step, the invoice screen (Send, move the date and send), the list's bulk Send. The automatic invoice sends none (erp-auto-invoice.md).

## What does the send do now?
- It reads `parasut_invoice_id` with the order row (`$invoice['order_parasut_invoice_id']`) and asks `erp_edoc_parasut_legacy_invoice_check($value, $get)` right after the type checks, before the invoice's own refusals (VAT, lines, products, account card, internet sale) and the buyer's card:
  - empty: goes on, nothing read.
  - the unsure mark (`parasut_order_invoice_unsure()`): refused, no request; the legacy create may have written the record.
  - a record id: `GET sales_invoices/{id}` (no `include`) through `erp_edoc_parasut_request()`, judged by `parasut_stored_record_state()` as the legacy buttons judge it (erp-parasut-legacy-stale-ids.md). 200: refused naming the record: keep the ERP invoice as a PDF, issue the record's e-document in Paraşüt if it never went through (2026.4.10 kept the id then too), or delete the record there first. 404 `Record was not found`, detail `SalesInvoice-<id>`: the id cleared (`_parasut_order_forget_record()`, only while the column holds it), the send goes on, the notice before any later refusal and in the success `message`. Anything else (no connection, no token, 401 `No access to company: <n>`, `No route matches.`, 5xx, 429): refused with Paraşüt's words.
- A refusal leaves the row `error` with no id ("Failed or rejected"); each Send reads again.

## Why refuse a record Paraşüt holds rather than take it over?
- Its lines, totals and e-document are the legacy press's, not the ERP invoice's; the ERP row's own unsure-mark search takes over only the ERP's own tries (erp-parasut-send-failures.md).

## Why is the reason not on the order card before a send?
- The card is provider-neutral (erp-edoc-driver-layer.md); a record's state takes a request. The mark or a kept id is a database read and could come through a new optional driver operation like `party_missing` (no registry.php change); not done here. After a refused send the card shows "Last answer: …" (`edoc_error`), the step still offered.

## What is still open?
- The unsure mark: with a provider on no screen clears it (the legacy buttons give way), so the ERP invoice stays a PDF.
- A cancelled legacy record answers 200 (`item_type` `cancelled`) and is refused. On 2026-10-24 one with no e-document was deleted after its cancel (API: 204, then 404), freeing the send.
- The 200 refusal names both ways: `include=active_e_document` (swagger.json) was not tried live.
- The reverse (ERP invoice sent, provider off, legacy Create Invoice): claim #1586.
- İşbaşı: its driver does not read the column, so a legacy-invoiced order gets a second e-document there.
- Nothing ran live (the demo key expired on 2026-10-25); the tests' 404 and 401 bodies are Paraşüt's answers of 2026-10-24 (#1544).

## Tests
- `tests/erp_edoc_parasut_legacy_invoiced_test.php`: the check per answer; the send refused on the mark before any request and before a line or card refusal; the source order of read, refusal, clear, own checks, buyer card.
