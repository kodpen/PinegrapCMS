---
name: erp-auto-invoice-earchive
description: Plan (approved 2026-10-19, not started): read ERP_AUTO_INVOICE_ON to create the invoice and queue the e-document on order status, revenue-threshold warnings (e-Fatura, e-Arşiv), internet-sale note
---

## Why?
- `ERP_AUTO_INVOICE_ON` is defined from `config.erp_auto_invoice_on` (ENUM `off|order_paid|order_shipped`, added by migration 2026.4.4) in `init.php:678` but nothing reads it and no settings card shows it; invoicing an order is a manual "next step" in the order flow. From 2026 taxpayers outside e-Fatura/e-Arşiv must issue every invoice as e-Arşiv through the GİB portal regardless of amount, and sellers on their own sites with gross sales of 500 Bin TL or more must join e-Fatura from the seventh month after that year (general threshold 3 Milyon TL). Competitors issue the e-document automatically on order status with several integrators.

## What is decided?
- Setting card in Settings › Ecommerce › ERP: the existing enum (off / when paid / when shipped) plus "only for orders with a billing tax number" and "skip orders below N".
- Writer: one function `erp_auto_invoice_for_order($order_id, $trigger)` in `includes/erp/order_bridge.php` that calls the existing `erp_invoice_from_order()` idempotently (one invoice per order; the ERP rule "no splitting an order into several invoices" holds) and, when an e-document provider is configured, enqueues the send in `erp_edoc_queue`. Triggers: `order_paid` (iyzico success, offline payment marked paid, on-account) and `order_shipped` (first shipment row). Called from the existing event points, never from the totals code.
- Queue worker: this plan depends on the `erp_edoc_queue` worker from `erp-edocument-leftovers.md`; until it exists the invoice is created and the send stays a panel step (the card says so).
- Internet-sale marker: e-Arşiv invoices created from web orders carry the "Bu satış internet üzerinden yapılmıştır" note and the payment method/date fields the UBL profile needs (verify with the İşbaşı test environment); POS sales do not.
- Threshold warnings: a monthly job sums gross web sales of the calendar year (orders complete, base currency) and System Status shows "e-Fatura threshold" (500 Bin TL internet rule and 3 Milyon TL general) with the deadline month; thresholds are settings so the owner updates them yearly without a release.
- Legacy Paraşüt order export (`includes/fn/parasut.php`) stays untouched.

## What remains?
1. Settings card + reader of the constant + tests on the trigger decision (S).
2. Writer + hooks at the two triggers + idempotency test (S–M).
3. Internet-sale fields in the UBL builder (`includes/erp/edoc/ubl.php`) (S).
4. Threshold job + status check (S).
5. Memory: update `docs/memory/erp/` order-bridge file; remove the "unread `ERP_AUTO_INVOICE_ON`" note from `shipping-management.md`.

## Open questions?
- Marketplace orders: auto-invoice too (the marketplace needs the invoice link) once `marketplace-trendyol-hepsiburada.md` pushes links; default on for them.

## Where to start?
- Step 1 and 2; read the `pinegrap-erp` skill, `includes/erp/order_flow.php` and `erp-edocument-leftovers.md`.
