---
name: erp-counter-sale-completion
description: Counter sale completion chain in add_order.php (order, invoice, receipt), when invoice is mandatory, issue_invoice_seen, and the till right check.
---

## What is the completion chain of `add_order.php`? (migration 4.60)
- Order closes → `erp_invoice_from_order()` → `erp_post_receipt()` (with `invoice_id`) → notification/error into the `view_order` liveform → redirect.
- If a payment method is chosen the invoice is mandatory: `$issue_invoice = !empty(issue_invoice) || pay_method !== ''`.
- Hidden `issue_invoice_seen` says the controls were on screen; only then is the choice written to `$_SESSION['ecommerce']['local_sale_payment']` (on a screen without the controls an empty choice is not remembered).
- Till right is `manage_erp_cash` (role < 3 always has it); without it the payment control is not printed at all, and the server also answers "no till right".
- A disabled checkbox does not enter the POST; harmless, because the server already treats the invoice as mandatory when a payment exists.

Related: docs/memory/erp/erp-local-sale.md
