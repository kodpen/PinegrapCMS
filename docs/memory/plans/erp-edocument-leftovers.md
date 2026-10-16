---
name: erp-edocument-leftovers
description: Open ERP work (e-document phase 3): Paraşüt send path, queue worker, İşbaşı live key, code lists, e-waybill, accountant sign-off; owner works on it locally
---

## What is left of the ERP plan?
ERP phases -1 to 9 are done (2026.4.4–2026.4.10). Open, all e-document (phase 3) or outside approval:
1. Paraşüt driver send path (`includes/erp/edoc/parasut.php`): API credentials never came from Paraşüt support. Design: docs/memory/plans/erp-parasut-driver-design.md
2. Worker for `erp_edoc_queue` (the table exists, nothing processes it) and the e-document error notification that hangs on it.
3. Logo İşbaşı live key (the owner enters it). The chain works end to end on the test environment.
4. Code lists from Logo: return and exemption codes, `eArchivePaymentType` for internet sales. GİB UBL-TR code lists v1.43 took effect 2026-09-14: review exemption and withholding codes.
5. e-waybill sending, only if the provider offers a "convert to e-document" call (Paraşüt v4 `shipment_documents` has none). Otherwise waybills stay internal and no `erp_waybills.edoc_*` columns are added.
6. Accountant sign-off: VUK 509 fields, the return paths below, the legal note. Questions: `docs/erp_malimusavir_sorulacaklar.md`.
7. Manual checks on dev (owner): phase 6 tools through the Claude connector, purchase-order PDF and supplier e-mail, My Account → My Invoices in a template and the cart for a customer with account prices.

## Who works on it?
- The owner, locally (scope decision 2026-10-13). Cloud threads do not touch `includes/erp/edoc/`.
- Driver contract: comment at the top of `includes/erp/edoc/registry.php`; layer: docs/memory/erp/erp-edoc-driver-layer.md. Provider comparison: project note `notes/efatura-saglayici-karsilastirma.md`.

## Which cancellation or return path applies?
A consumer who is not a taxpayer cannot issue an invoice; the return document comes from the buyer.

| Case | Path |
|---|---|
| Not issued yet | Delete the draft; nothing to reverse |
| Issued, e-document not sent | Cancel the invoice (`cancelled`), reverse account entry |
| Buyer is an e-invoice taxpayer | Buyer issues a return invoice; record it as a purchase invoice |
| Not a taxpayer, within 8 days | e-Archive cancellation (reported to GİB), queue `cancel` |
| Not a taxpayer, after 8 days | Return section signed by the consumer, `doc_type='return'` |

`earchive_cancel_deadline` and the remaining-days counter show the operator which path applies.

## Where does ERP hook into order cancellation?
- `process_order_cancellation()` (`includes/fn/ecommerce.php`) has no hook system; its only announcement is `api_webhook_enqueue('order.cancelled', …)`. ERP code goes right next to it, in the same order.

## When is phase 3 accepted?
- Taxpayer tax number gets an e-invoice, others an e-archive; a taxpayer with a TCKN gets an e-invoice too.
- GİB number comes back by poll (within 15 min) and by reconcile, also with a 5-minute cron.
- `gross_total` sent to and returned by the provider are identical, else nothing is issued.
- Provider down: the panel still works and the queue drains on its own afterwards.
- Two concurrent requests refresh the token once; a tampered webhook body changes no field.
- No invoice or waybill creating code left in `includes/fn/parasut.php`; `view_parasut_inbox.php` keeps working.

## Is the tax advice verified?
- No. UBL-TR exemption and withholding codes, VUK 509 fields, the e-archive cancellation window and the consumer return section were compiled from public sources and must be confirmed by the store's accountant.
