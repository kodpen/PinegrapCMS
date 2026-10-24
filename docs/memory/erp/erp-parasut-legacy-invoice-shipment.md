---
name: erp-parasut-legacy-invoice-shipment
description: The order screen's Paraşüt Create Invoice and E-Shipment on one order: shipment_included, the link between the two records, which press is refused, and the stock counted out twice before #1506
---

## What went wrong when both buttons were pressed? (#1506)
- Paraşüt keeps a sales invoice with `shipment_included` true unless told otherwise, and such an invoice takes its products out of stock itself (live: 10 to 8). Create Invoice sent no `shipment_included`, so an order that also got E-Shipment had its goods counted out twice at Paraşüt, once by the invoice and once by the outgoing `shipment_documents`.

## What does Create Invoice send after E-Shipment?
- An order holding `orders.parasut_shipment_id` gets `shipment_included` false and `relationships.shipment_documents` naming that document (`parasut_order_invoice_with_shipment()`). Paraşüt answers 201, moves no stock, and both records then name each other (the invoice's `shipment_documents`, the document's `invoices`).
- A document deleted on Paraşüt's screen meanwhile is read first and left out (erp-parasut-legacy-stale-ids.md); named, the create would answer 404 "Record was not found" and write nothing.
- Without a shipment document the body is unchanged (with shipment, Paraşüt's default).
- The ERP driver's sales invoice of such an order does the same: erp-parasut-shipped-order.md.

## When may E-Shipment go after Create Invoice?
- `parasut_create_shipment()` asks `parasut_shipment_invoice_link()` right after the shipped-order guard (#1407) and the token, before the buyer card, the product, the opening stock or the document are written. It reads `GET sales_invoices/{orders.parasut_invoice_id}`:
  - `shipment_included` false: the document goes with `relationships.invoices` naming it and takes the goods out once (live: 201, linked, stock moved by the document only).
  - `shipment_included` true: refused. The goods left stock with the invoice; Paraşüt also refuses to link such an invoice (422 "İrsaliyeli faturalar ile irsaliye ilişkilendirilemez."). A separate e-waybill needs the invoice issued without shipment.
  - 404 naming the invoice as not found (deleted on Paraşüt's screen): no invoice; the document goes unlinked and the id is cleared (erp-parasut-legacy-stale-ids.md). Any other 404 (a wrong company number) is no answer.
  - The unsure mark `?` (erp-parasut-legacy-invoice.md) or a read that fails or does not say: refused, nothing guessed. Create Invoice settles the mark first.
- No invoice on the order: E-Shipment goes as before.

## Which record carries the link?
- A shipment document cannot be linked afterwards: `PUT shipment_documents/{id}` with `relationships.invoices` answers 200 and links nothing. So the record written second names the first: the invoice in POST `sales_invoices`, the document in POST `shipment_documents`.

## What is still open?
- E-Shipment writes a shipment document inside Paraşüt only; nothing converts it to an e-waybill, so an invoice without shipment refers to a waybill GİB has not seen.
- E-Shipment after the ERP's own invoice (the provider switched off, so the buttons are back): `parasut_shipment_invoice_link()` reads only `orders.parasut_invoice_id`, not the ERP invoice's record, so one sent with the shipment is not seen and the goods are counted out twice.

## How was it checked?
- Demo company, 2026-10-24, branch code on dev orders: E-Shipment then Create Invoice gave an invoice with `shipment_included` false, linked both ways, stock moved once; Create Invoice then E-Shipment was refused before anything was written; an invoice without shipment then E-Shipment gave a linked document. All records deleted afterwards. The e-document step answers 422 there (no e-Archive integration), so what GİB gets for an invoice without shipment was not seen.

## Tests
- `tests/parasut_invoice_shipment_test.php`: both helpers, the body helper of the document, and that both functions call them before any write.
