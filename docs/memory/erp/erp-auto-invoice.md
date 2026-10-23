---
name: erp-auto-invoice
description: An order's invoice issued on its own once it is paid or shipped (config.erp_auto_invoice_on): setting, two filters, triggers, decision, writer, invoicing lock, after-response run, refusal notice
---

## What does the setting do?
- ERP settings card, "The invoice of a web order" (PR #697): `config.erp_auto_invoice_on` (ENUM since 2026.4.4, `ERP_AUTO_INVOICE_ON`) is `off` (the order screen's Issue the Invoice, default), `order_paid` or `order_shipped`; any other value is stored as `off`.
- Two filters under it (11.0.58, PR #958): `erp_auto_invoice_min_total` (BIGINT, minor units; typed in whole units, 0-10,000,000) and `erp_auto_invoice_tax_number_only`, read by `erp_auto_invoice_filters()`. All three are written only when the card posted the select.

## Where does it fire?
- `pg_erp_auto_invoice($order_id, $trigger, $after_response)` (`includes/fn/ecommerce.php`) returns at once unless `ERP_ENABLED` and the setting equals the trigger; only then is the ERP loaded.
- `order_paid`: the end of `submit_order()` (after the response), `pg_order_mark_paid()` (Payment Received; inside the ERP settlement's transaction it waits for the end of the request). `order_shipped`: `update_order()` when a recipient just shipped or gained a tracking number (also with `shipped => false`).
- Not hooked: ShipWorks, marketplace tracking (`mp_order_apply_tracking()`), API-created orders.

## Who decides?
- `erp_auto_invoice_decision()` (pure, `includes/erp/order_bridge.php`, `tests/erp_auto_invoice_test.php`) answers '' or a reason: off, trigger, status (not complete/exported), counter_sale (`type = local`, invoiced at the till), marketplace (the plan's open question), invoiced, cancelled_before (left to the operator), below_minimum, no_tax_number, unpaid, tax_number_differs (held, bell: erp-order-tax-number-difference.md). A filtered order gets no bell row.
- Paid is `paid_at > 0`, `erp_on_account = 1` or `total <= 0` (a gift card or discount covered it). Shipped asks for no payment.
- The minimum compares the whole sale (`total + gift_card_discount`); the tax number is the invoice's (`erp_auto_invoice_buyer_tax_number()`): the order's account, else the contact's, else the contact (the order keeps none). Empty or 11111111111 does not count.

## What does it write?
- `erp_auto_invoice_for_order()` runs the order card's own step: `erp_order_flow_state()` must offer `invoice`, then `erp_order_flow_run($id, 'invoice', 0)`. Same gates (the provider's fields on the account the invoice goes to or opens, #1363/#PR; nobody to bill), the waybill tie-back, `created_by = 0`, a log line.
- The e-document is not sent: there is no `erp_edoc_queue` worker, so it stays the order screen's next step.

## What happens when it cannot?
- A log line and a bell row `action = 'erp_auto_invoice'` (title = the reason, `order_id`, `order_total` = order number), once per order and reason (`erp_alert_auto_invoice_refused()`, under the order's lock), for `manage_erp`, linking to `view_order.php?id=N#erp_order_documents`. The reason is in the language of the request that tried.
- Said only while the order has no invoice (`erp_auto_invoice_refuse()`): of two tries at once, the one that finds the order invoiced is skipped `invoiced`, with no bell (#862).

## How is a second invoice for one order prevented?
- `erp_invoice_from_order()` holds the named lock `erp_order_invoice_lock_sql()` (database + order, MD5) for up to 10 s around the check and the write (`erp_invoice_from_order_locked()`); without named locks it writes as before. It also covers a double click.

## What runs after the response?
- `pg_erp_auto_invoice_defer()`: one shutdown function that closes the session, calls `fastcgi_finish_request()` where present, then invoices; never inside an open ERP transaction.
- `erp_archive_defer()` and `erp_mail_defer()` empty their queue as they run, so an invoice written during shutdown still gets its PDF and e-mail (PHP runs a shutdown function registered during shutdown).

Related: docs/memory/erp/erp-awaiting-transfer-orders.md
