---
name: erp-order-documents-flow
description: The order screen's documents card (includes/erp/order_flow.php): the four documents, step states, the one Next button, and why a step only calls its own screen's function.
---

## What does the card show?
- `erp_order_flow_card()` on `view_order.php` (`#erp_order_documents`): the order's invoice, its e-document (e-Fatura or e-Arşiv, `erp_order_flow_edoc_kind()` from `erp_edoc_invoice_expected_kind()`), the delivery note and its e-Delivery Note, each with where it stands. A cancelled or refunded order adds the reverse steps (erp-order-cancel-and-refund.md).
- `erp_order_flow_state($order_id)` → steps with state `done | ready | waiting | blocked | unavailable | later`, a reason, links, and `next`. Without a provider the two e-document steps are not drawn (unless this order's documents went through one before).

## How does a step run?
- `erp_order_flow.php` (POST, token, ERP gate) → `erp_order_flow_run($order_id, $action)`. Actions (`erp_order_flow_actions()`): `invoice`, `edoc_send`, `edoc_submit`, `edoc_poll`, `waybill`, `ewaybill`, `invoice_cancel`.
- Each step calls the same function its document screen calls, with the same gates (`erp_invoice_from_order()`, `erp_edoc_invoice_send/submit/poll()`, `erp_waybill_create()`, `erp_edoc_waybill_send()`). A new step is a new function there plus a branch here; the card never decides anything a document screen does not.
- Only a step the card offered runs: a stale page gets "This step is not available for the order any more".

## Which order do the steps follow?
- `next` is the first unfinished step that can run; a blocked step stops the chain. A document waiting for GİB does not hold the delivery note back (the answer can take hours). Nothing runs by itself; every step is a click (the automatic invoice reuses the invoice step: erp-auto-invoice.md).
- The invoice step warns, and stays offered, when the contact card's tax number is not the account's (erp-order-tax-number-difference.md).
- The invoice step is blocked before a number is spent when the provider would refuse the buyer (`erp_edoc_account_missing()`); the e-document step shows missing fields, the send gate or the date order and links to the repair on the invoice (`#edoc_fix`, `#edoc_date`; erp-edoc-send-readiness.md).
- A delivery note written before the invoice is tied to it when the invoice is issued, and its ship date and carrier fill the invoice's empty fields (`erp_waybill_write_back()`).
- No driver sends e-Delivery Notes yet, so with a provider that step reads unavailable.
