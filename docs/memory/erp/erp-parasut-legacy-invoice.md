---
name: erp-parasut-legacy-invoice
description: What the order screen's legacy Paraşüt "Create Invoice" sends: the ERP's lines and refusals (#1268), the buyer card (#1408), the e-document attributes (#1278)
---

## How does "Create Invoice" build the invoice? (#1268, #1274)
- Lines of the order's ERP invoice (`parasut_order_invoice_lines()`: `erp_order_lines()` on the charged lines, the ERP code loaded with or without the ERP on; erp-discounted-order-vat.md, erp-order-charges-vat.md). Written by the driver's `erp_edoc_parasut_invoice_payload()` in the base currency.
- Refused before any request (`parasut_order_invoice_refusal()`): lines not the order's total plus the gift card less `charges_left`, a rate Paraşüt does not take, a line below zero (#1297), an unreachable rate total, a line without a product. A sales invoice whose `net_total` differs is deleted before the e-document; one that cannot be deleted stays on the order.
- Stores with the buttons on settle their checkout charges like ERP stores (`pg_order_charges_settle_rates()`).
- Dev, demo company: #1182 (discount) 150.32, #1181 625.82, #1180 57.92, #1176 (57.93) 57.92.

## Which buyer card does it open? (#1408)
- `parasut_sync_contact()` opens it once per order (`orders.parasut_contact_id`) with `parasut_order_contact_attributes()`: the VKN/TCKN (`_parasut_order_identity()`) in `tax_number`; a person without a billing company or with 11 digits, as the driver. A person's TCKN used to go as an unlisted `tckn`; a card opened so may lack the number.

## What does its e-document carry? (#1278)
- The kind is asked of GİB before the buyer card is opened. `parasut_order_edoc_attributes()` gives the driver's `erp_edoc_parasut_edoc_attributes()` the order's `erp_order_sale_fields()` (erp-vuk-509-fields.md), dated by the sales invoice's day: a zero-rated line's exemption (`erp_vat_line_exemption_code()`), the commercial scenario and mailbox of an e-Invoice, an internet sale's `internet_sale` and `shipment` on e-Archive.
- An internet-sale e-Archive without web address, carrier or a valid carrier VKN/TCKN (`erp_edoc_parasut_document_missing()`) is refused before any request, an order with no shipping method too (the carrier comes from it), as the ERP's driver refuses its invoice.

## Tests
- `tests/parasut_order_invoice_test.php`: lines, refusals, 1,200 random orders against Paraşüt's arithmetic, e-document attributes, buyer card.
