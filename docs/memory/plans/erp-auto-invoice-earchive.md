---
name: erp-auto-invoice-earchive
description: Plan (approved 2026-10-19, in progress: steps 1, 1b, 2 done, PRs #697, #PRNUM): auto invoice once paid or shipped, with its two filters, built; internet-sale UBL fields and threshold warnings left
---

## Why?
- Invoicing an order was only a manual "next step" on the order card, and `config.erp_auto_invoice_on` (ENUM `off|order_paid|order_shipped`, 2026.4.4) had no reader. From 2026 taxpayers outside e-Fatura/e-Arşiv must issue every invoice as e-Arşiv through the GİB portal regardless of amount, and sellers on their own sites with gross sales of 500 Bin TL or more must join e-Fatura from the seventh month after that year (general threshold 3 Milyon TL). Competitors issue the e-document automatically on order status with several integrators.

## What is built?
- Steps 1-2 (PR #697): the ERP card's "The invoice of a web order" (the existing enum), `erp_auto_invoice_decision()` with tests, `erp_auto_invoice_for_order()` at the end of the checkout, on Payment Received and on a new shipment, the invoicing lock in `erp_invoice_from_order()`, refusals on the panel bell. Behaviour: docs/memory/erp/erp-auto-invoice.md.
- Step 1b (PR #PRNUM): the card's two filters, schema step 11.0.58 (`erp_auto_invoice_tax_number_only`, `erp_auto_invoice_min_total` in minor units), read in `erp_auto_invoice_decision()`.

## What is decided?
- Setting card in Settings › Ecommerce › ERP: the existing enum plus two filters, only buyers with a tax number (the invoice's: the account's, else the contact's; the order keeps none) and a smallest order (the whole sale, a gift card included). A filtered order is left to the order screen without a bell row.
- Writer: `erp_auto_invoice_for_order($order_id, $trigger)` in `includes/erp/order_bridge.php` runs the order card's own invoice step (`erp_order_flow_run()`, which calls `erp_invoice_from_order()`), so the card's gates and the waybill tie-back apply; one invoice per order (the ERP rule "no splitting an order into several invoices" holds). Triggers: `order_paid` (gateway payment at checkout, offline payment marked paid, on-account, nothing left to pay) and `order_shipped` (a recipient just shipped). Called from the existing event points, never from the totals code.
- Counter sales are never invoiced by it (the till invoices them); an order whose invoice was cancelled is left to the operator.
- Queue worker: the e-document send depends on the `erp_edoc_queue` worker from `erp-edocument-leftovers.md`; until it exists the invoice is created and the send stays a panel step (the card says so). Built that way.
- Internet-sale marker: e-Arşiv invoices created from web orders carry the "Bu satış internet üzerinden yapılmıştır" note and the payment method/date fields the UBL profile needs (verify with the İşbaşı test environment); POS sales do not.
- Threshold warnings: a monthly job sums gross web sales of the calendar year (orders complete, base currency) and System Status shows "e-Fatura threshold" (500 Bin TL internet rule and 3 Milyon TL general) with the deadline month; thresholds are settings so the owner updates them yearly without a release.
- Legacy Paraşüt order export (`includes/fn/parasut.php`) stays untouched.

## What remains?
3. Internet-sale fields in the UBL builder (`includes/erp/edoc/ubl.php`) (S).
4. Threshold job + status check (S).
5. When done: decisions move to docs/memory/erp/erp-auto-invoice.md and this file is deleted.

## Open questions?
- Marketplace orders: auto-invoice too (the marketplace needs the invoice link) once `marketplace-trendyol-hepsiburada.md` pushes links; default on for them. Until then the decision skips them.
- Once the queue worker exists: enqueue the e-document send right after the automatic invoice, as first planned.

## Where to start?
- Step 4 (no outside dependency); step 3 needs the İşbaşı test environment to check the UBL fields. Read docs/memory/erp/erp-auto-invoice.md first.
