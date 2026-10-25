---
name: erp-parasut-legacy-invoice
description: What the order screen's legacy Paraşüt "Create Invoice" sends: its lines and refusals (#1268), an unanswered create (#1416), the buyer card (#1408), its e-document (#1278), after E-Shipment (#1506)
---

## How does "Create Invoice" build the invoice? (#1268, #1274)
- Lines of the order's ERP invoice (`parasut_order_invoice_lines()`: `erp_order_lines()` on the charged lines, the ERP code loaded with or without the ERP on; erp-discounted-order-vat.md, erp-order-charges-vat.md). Written by the driver's `erp_edoc_parasut_invoice_payload()` in the base currency.
- Refused before any request (`parasut_order_invoice_refusal()`): lines not the order's total plus the gift card less `charges_left`, a rate Paraşüt does not take, a line below zero (#1297), an unreachable rate total, a line without a product. A sales invoice whose `net_total` differs is deleted before the e-document (the order's id cleared); one that cannot be deleted stays on the order.
- Before them, the order's status and its ERP invoice (`parasut_order_press_refusal()`, #1628): erp-parasut-legacy-order-gates.md.
- Stores with the buttons on settle their checkout charges like ERP stores (`pg_order_charges_settle_rates()`).
- Dev, demo company: #1182 (discount) 150.32, #1181 625.82, #1180 57.92, #1176 (57.93) 57.92.

## What if the create gets no answer? (#1416)
- Before `POST sales_invoices` the order gets the bare mark `?` (`ERP_EDOC_PARASUT_UNSURE`; date and contact are on the order). `parasut_order_invoice_id_after_create()`: the id, `''` after a 4xx, the mark kept after a timeout, 5xx or 2xx naming no record.
- `parasut_order_invoice_unsure()`: the list shows "Check Paraşüt", not the emblem; the order screen's button and status row a warning (`parasut_order_invoice_status_badge()`), never "Sent"; the button stays on.
- The next press searches as the ERP driver does (erp-parasut-send-failures.md): `parasut_order_invoice_find()` (contact, issue date, 20 pages), `_matches()` (the driver's match, issued records kept in). One draft: id kept, then total check and e-document; one issued: id kept, nothing sent; none: created; several or a failed search: refused, mark kept.

## Which buyer card does it use? (#1408, #1518, #1543)
- The card Paraşüt holds for the buyer's VKN/TCKN, else a new one carrying the order's identity and address: erp-parasut-legacy-buyer-card.md.

## What does its e-document carry? (#1278)
- The kind is asked of GİB before the buyer card is opened. `parasut_order_edoc_attributes()` gives the driver's `erp_edoc_parasut_edoc_attributes()` the order's `erp_order_sale_fields()` (erp-vuk-509-fields.md), dated by the sales invoice's day: a zero-rated line's exemption (`erp_vat_line_exemption_code()`), the commercial scenario and mailbox of an e-Invoice, an internet sale's `internet_sale` and `shipment` on e-Archive.
- An internet-sale e-Archive without web address, carrier or a valid carrier VKN/TCKN (`erp_edoc_parasut_document_missing()`) is refused before any request, an order with no shipping method too (the carrier comes from it), as the ERP's driver refuses its invoice.

## What if E-Shipment came first? (#1506)
- The invoice goes without shipment (`shipment_included` false), naming the shipment document (`parasut_order_invoice_with_shipment()`), so the goods are counted out once: erp-parasut-legacy-invoice-shipment.md.

## Tests
- `tests/parasut_order_invoice_test.php`: lines, refusals, 1,200 random orders against Paraşüt's arithmetic, e-document attributes, mark, search, badge, buyer card; `tests/order_quick_edit_test.php`: the list's mark.
