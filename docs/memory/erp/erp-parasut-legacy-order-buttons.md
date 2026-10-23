---
name: erp-parasut-legacy-order-buttons
description: The order screen's own Paraşüt buttons (Create Invoice, E-Shipment): the ERP's lines (#1268), e-document attributes (#1278), buyer card (#PR); when they give way to the ERP's documents card
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`), posted back as `parasut_action`; drawn when `parasut_order_buttons_on()` (`ENABLE_PARASUT`, company and client id set). They send the order straight to Paraşüt, outside the ERP's books. Status rows (Sent / Paraşüt ID) sit in the Order Information card.

## When are they not drawn?
- When `parasut_order_documents_in_erp()`: the ERP is on and an e-document provider is active (`erp_edoc_providers.is_active`). The order's invoice is then the ERP's ("Invoice and delivery documents" card); both on one order put the same sale into Paraşüt twice (#639). A post from a page opened before is refused, pointing to the card; a sent order keeps its status.
- Without the ERP, or with the ERP and no provider (paper invoices), the buttons work. The orders list's Excel export for Paraşüt is not affected.

## What does "Create Invoice" do on an order already sent?
- Nothing: it reads `orders.parasut_invoice_id` first and refuses before any request. The id is kept as soon as the sales invoice exists, also when the e-document failed or is converting (the job id is not kept), so the e-document is finished on that record in Paraşüt; the button is drawn disabled (#759, #1084).

## How does "Create Invoice" build the invoice? (#1268, #1274)
- Lines of the order's ERP invoice (`parasut_order_invoice_lines()`: `erp_order_lines()` on the charged lines, the ERP code loaded with or without the ERP on; erp-discounted-order-vat.md, erp-order-charges-vat.md). Written by the driver's `erp_edoc_parasut_invoice_payload()` in the base currency.
- Refused before any request (`parasut_order_invoice_refusal()`): lines not the order's total plus the gift card less `charges_left`, a rate Paraşüt does not take, a line below zero (#1297), an unreachable rate total, a line without a product. A sales invoice whose `net_total` differs is deleted before the e-document; one that cannot be deleted stays on the order.
- Stores with the buttons on settle their checkout charges like ERP stores (`pg_order_charges_settle_rates()`).
- Dev, demo company: #1182 (discount) 150.32, #1181 625.82, #1180 57.92, #1176 (57.93) 57.92.

## Which buyer card does it open? (#PR)
- `parasut_sync_contact()` opens it once per order (`orders.parasut_contact_id`) with `parasut_order_contact_attributes()`: the VKN/TCKN (`_parasut_order_identity()`) in `tax_number`; a person without a billing company or with 11 digits, as the driver. A person's TCKN used to go as an unlisted `tckn`; a card opened so may lack the number.

## What does its e-document carry? (#1278)
- The kind is asked of GİB before the buyer card is opened. `parasut_order_edoc_attributes()` gives the driver's `erp_edoc_parasut_edoc_attributes()` the order's `erp_order_sale_fields()` (erp-vuk-509-fields.md), dated by the sales invoice's day: a zero-rated line's exemption (`erp_vat_line_exemption_code()`), the commercial scenario and mailbox of an e-Invoice, an internet sale's `internet_sale` and `shipment` on e-Archive.
- An internet-sale e-Archive without web address, carrier or a valid carrier VKN/TCKN (`erp_edoc_parasut_document_missing()`) is refused before any request, an order with no shipping method too (the carrier comes from it), as the ERP's driver refuses its invoice.

## What is left?
- Removing the invoice and shipment code from `includes/fn/parasut.php` once no store relies on it (docs/memory/plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` keeps working.

## Tests
- `tests/parasut_test.php`: the buttons' rule, the error text; `tests/parasut_order_invoice_test.php`: lines, refusals, 1,200 random orders against Paraşüt's arithmetic, e-document attributes, buyer card.
