---
name: workspace-flows-webhook-start-events
description: Webhook events a flow can start on as well (reviews, returns, cart quotes, marketplace imports, subscriptions, form fees, stock changes); record, right, outputs, stock change kept on demand
---

## Which webhook events start flows too?
- `product.review.created`, `order.return.approved` / `.received` / `.refunded`, `cart_quote.created` / `.ordered`, `marketplace.order.imported`, `subscription.renewed` / `.failed` / `.cancelled`, `form.paid`, `inventory.changed` (#7261). Each is in `pg_event_workspace_events()` (kept from `pg_announce()`), `ws_events_catalog()` and `ws_flow_event_outputs()`.
- All sit in the shop block (`ECOMMERCE`). Reviews, cart quotes, subscriptions and form fees are listed only once their tables exist (`pg_reviews_ready()`, `pg_cart_quotes_ready()`, `pg_subscriptions_ready()`, `pg_form_payment_ready()`), as bookings are.

## What record and right does each have?
- Returns and marketplace imports: the order (payload `id`), right `ecommerce`.
- Cart quotes: the `cart_quote` tag (payload `id`).
- Subscriptions: a renewal names its order (`order_id`); a failure or cancellation the customer's contact (`customer_id`), none when the account has no contact. Right `ecommerce`; the contact is drawn by the reader's own rights.
- A review: the product, or a product group shown as one product (`item_type`, `item_id`); a comment on anything else names no record.
- `form.paid`: the submission (`id`), right `forms`, filter `page` like `form.submitted` (payload `form_id` is the form's page).
- `inventory.changed`: the product (`product_id`).
- None is written into watching channels (`ws_events_watched()` unchanged).

## Why is a stock change kept only on demand?
- `inventory.changed` comes with every tracked line of every sale. Kept always, it would fill `ws_events_in` and hold the run's batch (`WS_EVENTS_BATCH`) back from the events flows do wait for.
- `pg_event_workspace_on_demand()` lists it; `pg_event_record()` keeps it only when an active row of `ws_scheduled_actions` has an event rule naming it (`pg_event_workspace_awaited()`, one read per request and event). With the Workspace off every event is kept this way, by the anchors of flows alone (`automation-flows-gate.md`).

## Does the API's stock write reach the Workspace?
- Yes, through `api_webhook_enqueue()` (`includes/api/resources/inventory.php`): it calls `pg_event_record()` itself unless `$recorded` says `pg_announce()` already did. A second `pg_event_record()` beside it kept `inventory.changed` and `stock.low` twice, so each waiting flow started twice; removed.

## What do the start box's outputs give?
- Only single values the announcing code always sends: a return its order number, `return_id`, `reason` (a refund also `refund_method`); a subscription `customer_id`, `product_id`, `status`, `previous_status`, `order_id`, `retry_count`; a review `item_id`, `rating`, `status`; a stock change `product_id`, `quantity`. Amounts in minor units are not offered (`{{amount}}` reads the order).
- Tests: `tests/workspace_webhook_start_events_test.php`.
