---
name: erp-parasut-shipped-order
description: The ERP driver's sales invoice for an order the legacy E-Shipment already shipped: sent without shipment and naming the shipment document, so Paraşüt counts the goods out once (#PR)
---

## What went wrong? (#PR)
- Paraşüt keeps a sales invoice with `shipment_included` true unless told otherwise, and such an invoice takes its goods out of stock itself. The driver's `POST sales_invoices` sent no `shipment_included`. An order whose outgoing `shipment_documents` the order screen's legacy E-Shipment had written (`orders.parasut_shipment_id`) and that was then invoiced through the ERP had its goods counted out twice. Such an order was shipped before the ERP and a provider were switched on: from then on the legacy buttons give way (erp-parasut-legacy-order-buttons.md).

## What does the driver send now?
- `send_invoice` reads `orders.parasut_shipment_id` with the order's number and date, as the invoice's `order_shipment_id`.
- `erp_edoc_parasut_order_shipment_id()` takes it when the invoice has an `order_id` and the value is a record id (digits, kept as a string: ids outgrow a 32-bit integer). Empty, anything else, or a typed invoice: none.
- With one, `erp_edoc_parasut_invoice_payload()` adds `attributes.shipment_included` false and `relationships.shipment_documents` naming the document. Without, the body is the one sent before (no `shipment_included`: Paraşüt's default, shipment included).
- The legacy Create Invoice builds its body with the same function but passes no `order_shipment_id`; it adds the same two fields itself (`parasut_order_invoice_with_shipment()`, any non-empty value): erp-parasut-legacy-invoice-shipment.md. Returns and proformas never go to Paraşüt (`send_invoice` refuses them).

## Why name the document in the invoice?
- The record written second has to name the first: a shipment document cannot be linked afterwards (`PUT shipment_documents/{id}` with `relationships.invoices` answers 200 and links nothing).

## What did the live API show? (demo company, 2026-10-24)
- Branch code on dev order 443: test product with stock tracking, stock 5 by an inflow document, 4 after the outgoing document (standing in for E-Shipment). The ERP invoice of the order, sent by `erp_edoc_invoice_send()`: 201, `shipment_included` false, its `shipment_documents` the document, the document's `invoices` the invoice, stock still 4.
- The link also holds when the document sits on another card than the invoice (201, linked both ways, no stock moved). The legacy E-Shipment's card (`parasut_sync_contact()`) need not be the card the driver finds by number.
- A shipment document that no longer exists: Paraşüt answers 404 and writes nothing (#1506).
- A document already linked is named again: a second invoice while the first stands, or after the first is cancelled (`DELETE sales_invoices/{id}/cancel` on a plain record; it stays linked), answers 201, the document names both, no stock moves. A deleted invoice drops out of the document's `invoices`. So a rejected e-Invoice sent again, an order invoiced again after a cancel, and the rewrite after a delete go through.

## What is still open?
- A shipment document deleted on Paraşüt's screen: the send is refused (404, "Paraşüt would not take the invoice") on every try until `orders.parasut_shipment_id` is cleared; no screen clears it.
- What GİB gets for an invoice without shipment, and a cancelled e-Archive's link: the demo has no e-document integration (erp-parasut-driver.md).

## Tests
- `tests/erp_edoc_parasut_test.php`: `..._order_shipment_id`, `..._invoice_payload_legacy_shipment`, `..._send_invoice_reads_the_order_shipment`.
