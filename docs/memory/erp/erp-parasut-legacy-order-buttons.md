---
name: erp-parasut-legacy-order-buttons
description: The order screen's own Paraşüt buttons: Create Invoice sends the ERP's lines (#1268) and e-document attributes (#1278), never twice; when they give way to the ERP's documents card
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`), posted back as `parasut_action`; drawn when `parasut_order_buttons_on()` (`ENABLE_PARASUT`, company and client id set). They send the order straight to Paraşüt, outside the ERP's books. Status rows (Sent / Paraşüt ID) sit in the Order Information card.

## When are they not drawn?
- When `parasut_order_documents_in_erp()`: the ERP is on and an e-document provider is active. The order's invoice is then the ERP's ("Invoice and delivery documents" card); both would put the sale into Paraşüt twice (#639). A post from an older page is refused with a notice; an order already sent keeps its status rows.
- Without the ERP, or with no provider (paper invoices), the buttons work. The orders list's Paraşüt Excel export is unaffected.

## What does "Create Invoice" do on an order already sent?
- Nothing: it reads `orders.parasut_invoice_id` first and refuses before any request; the button is drawn disabled. The id is stored as soon as the sales invoice exists, also when the e-document failed or is converting (the job id is not kept), so the e-document is finished on that record in Paraşüt (#1084; before, each press raised another, #759).

## What if the create gets no answer? (#PR)
- Before `POST sales_invoices` the order gets the bare unsure mark `?` (`ERP_EDOC_PARASUT_UNSURE`; date and buyer card are on the order). `parasut_order_invoice_id_after_create()` reads the answer: the id, `''` after a 4xx, the mark kept after a timeout, 5xx or 2xx naming no record (`erp_edoc_parasut_answer_unsure()`). Deleting a wrong-total record clears it.
- With the mark the press is refused (status "Sent", ID `?`): the operator looks on Paraşüt for "Order #N" and issues its e-document, or creates the invoice, there. No search like the ERP driver's (erp-parasut-send-failures.md). Before, the next press wrote a second sales invoice.

## How does "Create Invoice" build the invoice? (#1268, #1274)
- Lines of the order's ERP invoice (`parasut_order_invoice_lines()`: `erp_order_lines()` on the charged lines, loaded with or without the ERP on), written by the driver's `erp_edoc_parasut_invoice_payload()` in the base currency.
- Refused before any request (`parasut_order_invoice_refusal()`): lines not the order's total plus the gift card less `charges_left`, a rate Paraşüt does not take, a line below zero (#1297), an unreachable rate total, a line without a product. A sales invoice whose `net_total` differs is deleted before the e-document; one that cannot be deleted stays on the order.
- Stores with the buttons on settle their checkout charges like ERP stores (`pg_order_charges_settle_rates()`).

## What does its e-document carry? (#1278)
- The kind is asked of GİB before the buyer card is opened. `parasut_order_edoc_attributes()` gives the driver's `erp_edoc_parasut_edoc_attributes()` the order's `erp_order_sale_fields()` (erp-vuk-509-fields.md): a zero-rated line's exemption, an e-Invoice's scenario and mailbox, an internet sale's `internet_sale` and `shipment` on e-Archive.
- An internet-sale e-Archive without web address, carrier (from the shipping method) or a valid carrier VKN/TCKN (`erp_edoc_parasut_document_missing()`), or with no shipping method, is refused before any request.

## What is left?
- Removing the invoice and shipment code from `includes/fn/parasut.php` once no store relies on it (docs/memory/plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` keeps working.

## Tests
- `tests/parasut_test.php`: the buttons' rule, the error text; `tests/parasut_order_invoice_test.php`: lines, refusals, random orders against Paraşüt's arithmetic, e-document attributes, the unsure mark.
