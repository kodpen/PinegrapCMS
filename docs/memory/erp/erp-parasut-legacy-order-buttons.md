---
name: erp-parasut-legacy-order-buttons
description: Order screen's own Paraşüt buttons: Create Invoice's ERP lines (#1268) and e-document attributes (#1278), E-Shipment's identity check (#PR), when the ERP's documents card replaces them
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`), posted back as `parasut_action`; drawn when `parasut_order_buttons_on()` (`ENABLE_PARASUT`, company and client id set). They send the order to Paraşüt outside the ERP's books. Status rows are in the Order Information card.

## When are they not drawn?
- When `parasut_order_documents_in_erp()`: the ERP is on and an e-document provider is active (`erp_edoc_providers.is_active`). The order's invoice is then the ERP's ("Invoice and delivery documents" card); both on one order put the same sale into Paraşüt twice (#639). A post from a page opened before is refused, pointing to the card; an order already sent keeps its status rows.
- Without the ERP, or with the ERP and no provider (paper invoices), the buttons work. The orders list's Paraşüt Excel export is not affected.

## What does "Create Invoice" do on an order already sent?
- Nothing: it reads `orders.parasut_invoice_id` first and refuses before any request. The id is kept once the sales invoice exists, also when the e-document failed or is converting (it is finished on that record in Paraşüt); the button is drawn disabled. Before #1084 each press raised another sales invoice (#759).

## How does "Create Invoice" build the invoice? (#1268, #1274)
- Lines of the order's ERP invoice (`parasut_order_invoice_lines()`: `erp_order_lines()` on the charged lines): the discount spread over the lines, VAT per rate on their sum, shipping, surcharge and instalment charge with their VAT inside at the goods' rate, a charge that leaves a rate's total out of reach a kurus lower. Written by the driver's `erp_edoc_parasut_invoice_payload()` in the base currency.
- Refused before any request (`parasut_order_invoice_refusal()`): lines not the order's total plus the gift card less `charges_left`, a rate Paraşüt does not take, a line below zero (#1297), an unreachable rate total, a line without a product. A sales invoice whose `net_total` differs is deleted before the e-document; one that will not delete stays on the order.
- Stores with the buttons on settle their checkout charges like ERP stores (`pg_order_charges_settle_rates()`).
- Dev, demo company: #1182 (discount) 150.32, #1181 625.82, #1180 57.92, #1176 (57.93) 57.92.

## What does its e-document carry? (#1278)
- The kind is asked of GİB before the buyer card is opened. `parasut_order_edoc_attributes()` gives the driver's `erp_edoc_parasut_edoc_attributes()` the order's `erp_order_sale_fields()` (erp-vuk-509-fields.md), dated by the sales invoice's day: a zero-rated line's exemption, the commercial scenario and mailbox of an e-Invoice, an internet sale's `internet_sale` and `shipment` on e-Archive.
- An internet-sale e-Archive without web address, carrier (from the shipping method, so an order without one too) or a valid carrier VKN/TCKN (`erp_edoc_parasut_document_missing()`) is refused before any request.

## Which VKN/TCKN does "E-Shipment" check? (#PR)
- `_parasut_shipment_identity_refusal()`: with `PARASUT_TC_IN_FIELD` custom_field_1/2, the order's field (the buyer card's number, `_parasut_order_identity()`), named as on the order screen ("Custom Field #1"); with "do not use" (the default) or tax_number, the contact's `tax_number`. Before, custom-field stores were refused with the number on the order.

## What is left?
- Removing the invoice and shipment code from `includes/fn/parasut.php` once no store uses it (plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` stays.

## Tests
- `tests/parasut_test.php`: the buttons' rule, the error text, E-Shipment's identity check; `tests/parasut_order_invoice_test.php`: lines, refusals, 1,200 random orders against Paraşüt's arithmetic, e-document attributes.
