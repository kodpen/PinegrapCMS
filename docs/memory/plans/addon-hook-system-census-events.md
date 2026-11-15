---
name: addon-hook-system-census-events
description: Add-on hook system AH1 census, events part: pg_announce(), direct api_webhook_enqueue() and erp_event() doors, the event catalogue, where order, stock and other moments fire
---

## Which doors announce a moment?
- `pg_announce($event, $data)` (`includes/fn/events.php`): records it for the Workspace (`pg_event_record()`), then runs in-process listeners (page cache dirty, `pg_stock_notify_heard()`, ActivityPub `pg_ap_heard()` for `page.*`/`form.*`, `pg_contact_group_joined_heard()`, `pg_notify_announced()`), then queues it with `api_webhook_enqueue($event, $data, true)`.
- `api_webhook_enqueue()` called directly (`includes/api/outbound/webhooks.php`): about 25 calls, mostly `includes/api/resources/*.php`, plus `order_completion.php` (`order.created`), `process_order_cancellation()` (`order.cancelled`), `update_order.php` and `shipworks.php` (`order.shipped`), `product_builder.php` (`product.deleted`), `custom_form.php` (`form.submitted`). It records for the Workspace too, but the `pg_announce()` listeners above do not run.
- `erp_event()` (`includes/erp/events.php`): ERP audit trail, ERP alerts, `pg_event_record()`, then `api_webhook_enqueue()`; also skips the `pg_announce()` listeners.
- So no single function sees every moment today; a hook placed in `pg_announce()` alone would miss the direct-enqueue and ERP paths.

## Which event names exist?
- The catalogue is `api_webhook_events()` (`includes/api/outbound/webhooks.php`) plus `api_module_contributions('webhook_events')` from `includes/api/modules.php`: `erp_webhook_events()`, `ws_webhook_events()`, the flows-alone and mail-alone twins. The core list wins a name a module repeats. A name outside the catalogue is dropped by `api_webhook_event_is_valid()`.
- Core families: `order.*` (created, status_changed, shipped, delivered, cancelled, return.requested/approved/received/refunded), `marketplace.order.*` (imported, cancelled, shipped), `cart_quote.*`, `subscription.*`, `booking.*`, `credit.*`, `mail.bounced`, `mail.complained`, `whatsapp.received`, `commission.status_changed`, `affiliate.approved`.
- More core: `product.*` (created, updated, deleted, review.created), `inventory.changed`, `stock.low`, `customer.*` (created, updated, deleted, consent_changed), `user.*`, `contact_group.members_changed`, `page.created`, `page.updated`, `experiment.started/ended`, `product_group.*`, `zone.*`, `tax_zone.*`, `shipping_method.*`, `currency.*`, `offer.*`, `file.created`, `form.submitted`, `form.paid`, `form.submission.deleted`, `calendar_event.reserved`, `campaign.sent`.
- Modules: 32 `erp.*` names (invoice, receipt, quote, purchase_order, waybill, edoc ...) and about 30 `workspace.*` names (task, message, approval, flow.run, wtable.row ...).
- The Workspace hears a fixed subset (`pg_event_workspace_events()`), stored in `ws_events_in`.

## Where do the commerce moments fire?
- Order placed: `pg_order_complete()` (`includes/fn/order_completion.php`, direct enqueue) and `pg_local_order_complete()` (`includes/fn/local_order.php`, `pg_announce`).
- Status changed: `pg_order_quick_update()` (`includes/fn/ecommerce.php`), `view_order.php` (status save), `includes/api/resources/orders.php`, `includes/workspace/changes.php`.
- Shipped / delivered / cancelled: `update_order.php`, `shipworks.php`, `includes/api/outbound/orders.php` (shipped); `pg_shipment_delivered()` (`includes/fn/shipping.php`); `process_order_cancellation()` (`includes/fn/ecommerce.php`).
- Stock: `pg_product_stock_changed()` in `includes/fn/ecommerce.php` builds `inventory.changed` / `stock.low` and announces them; the ERP and API stock paths announce their own.
- Variable-name calls build the name in one place: `pg_return_announce()` (`includes/fn/returns.php`), `includes/fn/cart_quotes.php`, `includes/fn/subscription_renewal.php`, `includes/fn/booking_messages.php`, `includes/api/outbound/orders.php`, `includes/fn/store_zones.php`, `store_currencies.php`, `shipping_methods.php`, `experiments_sheet.php`, `edit_offer_f.php`.
