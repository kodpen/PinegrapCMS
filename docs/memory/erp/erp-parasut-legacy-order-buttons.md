---
name: erp-parasut-legacy-order-buttons
description: The order screen's Paraşüt buttons (Create Invoice, E-Shipment): the ERP's card (#639), a sent order (#1084, #1407), E-Shipment's VKN/TCKN check (#1418), stock movement lines (#1458)
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`), posted back as `parasut_action`; drawn when `parasut_order_buttons_on()` (`ENABLE_PARASUT`, company and client id set). They send the order straight to Paraşüt, outside the ERP's books; status rows sit in the Order Information card. What Create Invoice sends: erp-parasut-legacy-invoice.md.

## When are they not drawn?
- When `parasut_order_documents_in_erp()`: the ERP is on and an e-document provider is active (`erp_edoc_providers.is_active`). The order's invoice is then the ERP's ("Invoice and delivery documents" card); both on one order put the same sale into Paraşüt twice (#639). A post from a page opened before is refused, pointing to the card; a sent order keeps its status.
- Without the ERP, or with the ERP and no provider (paper invoices), the buttons work. The orders list's Paraşüt Excel export is not affected.
- Nor for an order not complete or exported, nor Create Invoice for one the ERP invoiced via a provider: erp-parasut-legacy-order-gates.md (#1628).

## What do the buttons do on an order already sent?
- Each reads the record its id (`orders.parasut_invoice_id`, `parasut_shipment_id`) names: refused while Paraşüt holds it, left out if deleted there (#1544, erp-parasut-legacy-stale-ids.md). The mark `?` is no id (erp-parasut-legacy-invoice.md). The invoice id stays once the sales invoice exists, even if its e-document failed or is converting: finish it in Paraşüt. Before #1084 each press raised another invoice and e-document (#759); before #1407 another outgoing `shipment_documents` (stock out twice). Both on one order (#1506): erp-parasut-legacy-invoice-shipment.md.

## Which VKN/TCKN does "E-Shipment" check? (#1418)
- `_parasut_shipment_identity_refusal()`, asked by the screen without a shipment id, by `parasut_create_shipment()` after clearing a deleted one: with `PARASUT_TC_IN_FIELD` custom_field_1/2, the order's field (what the buyer card is opened with, `_parasut_order_identity()`), named as on the order screen ("Custom Field #1"); with "do not use" (the default) or tax_number, the contact's `tax_number`, as before. Before, custom-field stores were refused with the number on the order.

## What does "E-Shipment" send? (#1458)
- `POST shipment_documents` (`inflow` false) with the stock-tracked lines as `relationships.stock_movements`, each `parasut_shipment_stock_movement()`: `quantity`, `product`, `warehouse`. A line without a Paraşüt product is refused before the POST. Before #1458 the lines went as `details` (`shipment_document_details`), which Paraşüt answers 422 "Stok kalemleri doldurulmalı": the button never created a document. The list endpoint rejects `include=details` (400); read them with `include=stock_movements`. Checked live on the demo (2026-10-24).
- Stock step: erp-parasut-legacy-shipment-stock.md; deleted products: erp-parasut-legacy-shipment-deleted-products.md.
- A product counts as tracked by `inventory_tracking` alone (`parasut_product_stock_tracked()`); v4 has no `product_type`, so the old check failed and every press PUT the product, once into a 429. Now only an untracked one gets PUT `{inventory_tracking: true}`.
- `_parasut_request()` sends a 429 once more after a full rate window, as the ERP driver does.

## What is left?
- Removing the invoice and shipment code from `includes/fn/parasut.php` once no store relies on it (plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` keeps working.

## Tests
- `tests/parasut_test.php`: the buttons' rule, the error text, E-Shipment on a sent order, E-Shipment's identity check.
- `tests/parasut_shipment_test.php`: the stock movement line, both shipment requests, the tracking check, the 429 retry.
