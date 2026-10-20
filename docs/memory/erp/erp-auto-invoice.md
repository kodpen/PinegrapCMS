---
name: erp-auto-invoice
description: An order's invoice issued on its own once it is paid or shipped (config.erp_auto_invoice_on): setting, triggers, decision, writer, invoicing lock, after-response run, refusal notice
---

## What does the setting do? (PR #697)
- ERP settings card, "The invoice of a web order": `config.erp_auto_invoice_on` (ENUM since 2026.4.4, `ERP_AUTO_INVOICE_ON`) is `off` (the order screen's Issue the Invoice, default), `order_paid` or `order_shipped`. Written only when posted; any other value is stored as `off`.
- Not built: the plan's "only orders with a billing tax number" and "skip orders below N" need two config columns (owner approval pending): docs/memory/plans/erp-auto-invoice-earchive.md.

## Where does it fire?
- `pg_erp_auto_invoice($order_id, $trigger, $after_response)` (`includes/fn/ecommerce.php`) returns at once unless `ERP_ENABLED` and the setting equals the trigger; only then is the ERP loaded.
- `order_paid`: the end of `submit_order()` (after the response), `pg_order_mark_paid()` (Payment Received; the ERP settlement calls it inside its transaction, so it waits for the end of the request). `order_shipped`: `update_order()` when a recipient was just shipped or gained a tracking number (also with `shipped => false`, the Workspace write).
- Not hooked: ShipWorks, marketplace tracking (`mp_order_apply_tracking()`), orders created through the API.

## Who decides?
- `erp_auto_invoice_decision()` (pure, `includes/erp/order_bridge.php`, `tests/erp_auto_invoice_test.php`) answers '' or a reason: off, trigger, status (not complete/exported), counter_sale (`type = local`, invoiced at the till), marketplace (open question of the plan), invoiced, cancelled_before (a cancelled invoice is left to the operator), unpaid.
- Paid is `paid_at > 0`, `erp_on_account = 1` or `total <= 0` (a gift card or discount covered it). Shipped asks for no payment.

## What does it write?
- `erp_auto_invoice_for_order()` runs the order card's own step: `erp_order_flow_state()` must offer `invoice`, then `erp_order_flow_run($id, 'invoice', 0)`. Same gates (account card fields the e-document provider needs, nobody to bill), the waybill tie-back, `created_by = 0`, log "Order (#N) was invoiced automatically once it was paid: X".
- The e-document is not sent: there is no `erp_edoc_queue` worker, so it stays the order screen's next step.

## What happens when it cannot?
- A log line and a bell row `action = 'erp_auto_invoice'` (title = the reason, `order_id`, `order_total` = order number) once per order and reason (`erp_alert_auto_invoice_refused()`), shown to `manage_erp`, linking to `view_order.php?id=N#erp_order_documents`. The reason is in the language of the request that tried (the visitor's at checkout).

## How is a second invoice for one order prevented?
- `erp_invoice_from_order()` holds the named lock `erp_order_invoice_lock_sql()` (database + order, MD5) for up to 10 s around the check and the write (`erp_invoice_from_order_locked()`); a server without named locks writes as before. Sandbox: three writers on each of ten orders gave ten invoices; without the lock every order got two or three. This also covers a double click on Issue the Invoice.

## What runs after the response?
- `pg_erp_auto_invoice_defer()`: one shutdown function that closes the session, calls `fastcgi_finish_request()` where present and then invoices; it does not run inside an ERP transaction left open.
- `erp_archive_defer()` and `erp_mail_defer()` empty their queue as they run, so an invoice written during shutdown registers a new shutdown function (PHP runs it) and still gets its PDF and e-mail.

Related: docs/memory/erp/erp-awaiting-transfer-orders.md
