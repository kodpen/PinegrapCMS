---
name: erp-auto-invoice-earchive
description: Plan (approved 2026-10-19): web orders invoiced on their own once paid or shipped, with two filters, the e-Fatura threshold warning and the internet-sale UBL fields of an e-Arşiv invoice
---

## Why?
- Invoicing an order was only a manual "next step" on the order card, and `config.erp_auto_invoice_on` (ENUM `off|order_paid|order_shipped`, 2026.4.4) had no reader. From 2026 taxpayers outside e-Fatura/e-Arşiv must issue every invoice as e-Arşiv through the GİB portal regardless of amount, and sellers on their own sites with gross sales of 500 Bin TL or more must join e-Fatura from the seventh month after that year (general threshold 3 Milyon TL). Competitors issue the e-document automatically on order status with several integrators.

## What is decided?
- Internet-sale marker: e-Arşiv invoices created from web orders carry the "Bu satış internet üzerinden yapılmıştır" note and the payment method/date fields the UBL profile needs (verify with the İşbaşı test environment); POS sales do not.
- Legacy Paraşüt order export (`includes/fn/parasut.php`) stays untouched.

## What remains?
- Done: 1-2 #697, 1b #958, 4 #1773, marketplace orders #3692.

3. Internet-sale fields in the UBL builder (`includes/erp/edoc/ubl.php`) (S).
5. When done: decisions move to docs/memory/erp/erp-auto-invoice.md and this file is deleted.

## Open questions?
- Once the queue worker exists: enqueue the e-document send right after the automatic invoice, as first planned.

## Where to start?
- Only step 3 is left (the marketplace switch is built, #3692); it waits for the İşbaşı test environment, to check the internet-sale note and payment fields against the provider's UBL profile (no field is guessed, `erp-edoc-driver-layer.md`). Read docs/memory/erp/erp-auto-invoice.md first.
