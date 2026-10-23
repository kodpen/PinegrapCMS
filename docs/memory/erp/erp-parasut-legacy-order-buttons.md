---
name: erp-parasut-legacy-order-buttons
description: The order screen's own Paraşüt buttons (Create Invoice, E-Shipment): when they give way to the ERP's documents card (#639), what they do on an order already sent (#1084, #1407)
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`), posted back as `parasut_action`; drawn when `parasut_order_buttons_on()` (`ENABLE_PARASUT`, company and client id set). They send the order straight to Paraşüt, outside the ERP's books. Status rows (Sent / Paraşüt ID) sit in the Order Information card. What Create Invoice sends: erp-parasut-legacy-invoice.md.

## When are they not drawn?
- When `parasut_order_documents_in_erp()`: the ERP is on and an e-document provider is active (`erp_edoc_providers.is_active`). The order's invoice is then the ERP's ("Invoice and delivery documents" card); both on one order put the same sale into Paraşüt twice (#639). A post from a page opened before is refused, pointing to the card; a sent order keeps its status.
- Without the ERP, or with the ERP and no provider (paper invoices), the buttons work. The orders list's Excel export for Paraşüt is not affected.

## What do the buttons do on an order already sent?
- Nothing: each refuses before any request when its id (`orders.parasut_invoice_id`, `parasut_shipment_id`) holds a record's id, and is drawn disabled. The unsure mark `?` is not one (erp-parasut-legacy-invoice.md). The invoice id is kept once the sales invoice exists, even if its e-document failed or is converting (job id not kept): finish it in Paraşüt. Before #1084 each press raised another sales invoice and e-document (#759); before #1407 each E-Shipment press posted another outgoing `shipment_documents` (stock out twice, first id overwritten).

## What is left?
- Removing the invoice and shipment code from `includes/fn/parasut.php` once no store relies on it (plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` keeps working.

## Tests
- `tests/parasut_test.php`: the buttons' rule, the error text, E-Shipment on a sent order.
