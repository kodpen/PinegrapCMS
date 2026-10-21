---
name: erp-parasut-legacy-order-buttons
description: The order screen's own Paraşüt buttons (Create Invoice, E-Shipment, includes/fn/parasut.php) and when they give way to the ERP's invoice and delivery documents card
---

## What are the legacy Paraşüt buttons?
- `view_order.php` toolbar: "Create Invoice" (`parasut_create_invoice()`) and "E-Shipment" (`parasut_create_shipment()`), posted back as `parasut_action`; drawn when `ENABLE_PARASUT` and the Paraşüt company and client id are set. They send the order straight to Paraşüt, outside the ERP's books. The status rows (Sent / Paraşüt ID) sit in the Order Information card.

## When are they not drawn?
- When `parasut_order_documents_in_erp()` (includes/fn/parasut.php): the ERP is on and an e-document provider is switched on (`erp_edoc_providers.is_active`; before 4.61 `ERP_PARASUT_ENABLED`). The order's invoice is then the ERP's ("Invoice and delivery documents" card) and the provider takes it to GİB; both on one order put the same sale into Paraşüt twice (seen on dev: "Fatura Kes" in the toolbar beside the card's "Faturayı Kes"). Since #639.
- A post that still arrives (a page opened before the provider was switched on) is refused with a notice pointing to the card.
- An order already sent from the old buttons keeps its status rows.
- Without the ERP, or with the ERP and no provider (its invoices stay on paper), the buttons work as before. The orders list's Excel export for Paraşüt is not affected.

## What does "Create Invoice" do on an order already sent?
- Nothing: `parasut_create_invoice()` reads `orders.parasut_invoice_id` first and refuses ("This order already has a sales invoice in Paraşüt (ID: …)…") before any token or Paraşüt request. The id is kept as soon as the sales invoice exists, also when its e-document failed or the conversion was still running, and the job id is not kept, so the e-document is finished on that record in Paraşüt. The button is drawn disabled with the Sent badge.
- Before PR #1084 (issue #759) every press posted a new sales invoice and a new e-Archive/e-Invoice: a second document for the same sale at GİB.

## What is left?
- Removing the invoice and shipment code from `includes/fn/parasut.php` once no store relies on it (acceptance item of docs/memory/plans/erp-edocument-leftovers.md); `view_parasut_inbox.php` keeps working.

## Tests
- `tests/parasut_test.php`: `test_parasut_order_documents_in_erp` (the rule).
