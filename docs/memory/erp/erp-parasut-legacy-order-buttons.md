---
name: erp-parasut-legacy-order-buttons
description: The order screen's own Paraşüt buttons: Create Invoice sends the ERP's lines (#1268) and e-document attributes (#1278), never twice; when they give way to the ERP's documents card
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`); drawn when `parasut_order_buttons_on()` (`ENABLE_PARASUT`, company and client id set). They send the order to Paraşüt outside the ERP's books.

## When are they not drawn?
- When `parasut_order_documents_in_erp()`: the ERP and an e-document provider are on. The invoice is then the ERP's ("Invoice and delivery documents" card); both would put the sale into Paraşüt twice (#639). A post from an older page is refused; an order already sent keeps its status rows.
- Without the ERP, or with no provider (paper invoices), they work. The list's Paraşüt Excel export is unaffected.

## What does "Create Invoice" do on an order already sent?
- Nothing: an id in `orders.parasut_invoice_id` refuses it before any request; the button is disabled. The id is stored once the sales invoice exists, also when the e-document failed or is converting (job id not kept), so the e-document is finished there (#1084; before, each press raised another, #759).

## What if the create gets no answer? (#PR)
- Before `POST sales_invoices` the order gets the bare mark `?` (`ERP_EDOC_PARASUT_UNSURE`; date and contact are on the order). `parasut_order_invoice_id_after_create()`: the id, `''` after a 4xx, the mark kept after a timeout, 5xx or 2xx naming no record.
- `parasut_order_invoice_unsure()`: the list shows "Check Paraşüt", not the emblem; the order screen's button and status row a warning (`parasut_order_invoice_status_badge()`), never "Sent"; the button stays on.
- The next press searches as the ERP driver does (erp-parasut-send-failures.md): `parasut_order_invoice_find()` (contact, issue date, 20 pages), `_matches()` (the driver's match, issued records kept in). One draft: id kept, then total check and e-document; one issued: id kept, nothing sent; none: created; several or a failed search: refused, mark kept.

## How does "Create Invoice" build the invoice? (#1268, #1274)
- Lines of the order's ERP invoice (`parasut_order_invoice_lines()`: `erp_order_lines()` on the charged lines, loaded with or without the ERP on), written by the driver's `erp_edoc_parasut_invoice_payload()` in the base currency.
- Refused before any request (`parasut_order_invoice_refusal()`): lines not the order's total plus gift card less `charges_left`, a rate Paraşüt does not take, a line below zero (#1297), an unreachable rate total, a line without a product. A record whose `net_total` differs is deleted before the e-document; one not deleted stays on the order.
- Stores with the buttons on settle checkout charges like ERP stores (`pg_order_charges_settle_rates()`).
- Dev, demo company: #1182 (discount) 150.32, #1181 625.82, #1180 57.92, #1176 (57.93) 57.92.

## What does its e-document carry? (#1278)
- The kind is asked of GİB before the buyer card is opened. `parasut_order_edoc_attributes()` gives the driver's `erp_edoc_parasut_edoc_attributes()` the order's `erp_order_sale_fields()` (erp-vuk-509-fields.md): a zero-rated line's exemption, an e-Invoice's scenario and mailbox, an internet sale's `internet_sale` and `shipment` on e-Archive.
- An internet-sale e-Archive without web address, carrier (from the shipping method) or valid carrier VKN/TCKN (`erp_edoc_parasut_document_missing()`), or with no shipping method, is refused up front.

## What is left?
- Removing this code from `includes/fn/parasut.php` once no store relies on it (docs/memory/plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` stays.

## Tests
- `tests/parasut_test.php`: buttons' rule, error text; `tests/parasut_order_invoice_test.php`: lines, refusals, 1,200 random orders, e-document attributes, mark, search, badge; `tests/order_quick_edit_test.php`: the list's mark.
