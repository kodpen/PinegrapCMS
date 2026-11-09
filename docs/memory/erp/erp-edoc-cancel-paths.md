---
name: erp-edoc-cancel-paths
description: Which cancel or return path an invoice takes (draft, unsent, taxpayer buyer, consumer within or after 8 days), the order cancellation hook, the e-document acceptance list, unverified tax advice
---

## Which cancellation or return path applies?
A consumer who is not a taxpayer cannot issue an invoice; the return document comes from the buyer.

| Case | Path |
|---|---|
| Not issued yet | Delete the draft; nothing to reverse |
| Issued, e-document not sent | Cancel the invoice (`cancelled`), reverse account entry |
| Buyer is an e-invoice taxpayer | Buyer issues a return invoice; record it as a purchase invoice |
| Not a taxpayer, within 8 days | e-Archive cancellation (reported to GİB), queue `cancel` |
| Not a taxpayer, after 8 days | Return section signed by the consumer, `doc_type='return'` |

- How the screens run it: `erp-edoc-send-and-cancel.md`, `erp-order-cancel-and-refund.md`.
- `erp_invoices.earchive_cancel_deadline` (2026.4.4) was meant for a remaining-days counter that shows the operator which path applies; nothing writes or reads it yet. The Paraşüt driver asks the provider's `cancellable_until` instead (`erp-parasut-driver.md`).

## Where does ERP code hook order cancellation?
- `process_order_cancellation()` (`includes/fn/ecommerce.php`) has no hook system; its only announcement is `api_webhook_enqueue('order.cancelled', …)`. ERP code goes right next to it, in the same order.

## When is the e-document work (phase 3) accepted?
- A taxpayer's tax number gets an e-invoice, others an e-archive; a taxpayer with a TCKN gets an e-invoice too.
- The GİB number comes back by poll (within 15 min) and by reconcile, also with a 5-minute cron.
- `gross_total` sent to and returned by the provider are identical, else nothing is issued.
- Provider down: the panel still works and the queue drains on its own afterwards.
- Two concurrent requests refresh the token once; a tampered webhook body changes no field.
- No invoice or waybill creating code left in `includes/fn/parasut.php` (the order screen's buttons still use it without the ERP and give way with the ERP and a provider, `erp-parasut-legacy-order-buttons.md`); `view_parasut_inbox.php` keeps working.

## Is the tax advice verified?
- No. UBL-TR exemption and withholding codes, VUK 509 fields, the e-archive cancellation window and the consumer return section were compiled from public sources and must be confirmed by the store's accountant (questions: `docs/erp_malimusavir_sorulacaklar.md`).
