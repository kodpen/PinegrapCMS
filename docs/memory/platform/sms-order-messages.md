---
name: sms-order-messages
description: Order SMS (received, shipped, delivered): templates in connect_apps sms_templates, events off by default, where each is queued, references, what is not texted
---

## What sends an order's text message?
- `includes/fn/sms_messages.php`. Three events (`pg_sms_message_events()`): `order_received` (kind `order`), `order_shipped` and `order_delivered` (kind `shipping`). Each is queued with `pg_sms_enqueue()`, never sent in the request, to the order's `billing_phone_number`; a number that is not a mobile, an event switched off or SMS not set up sends nothing and stops nothing.
- Every event is off until switched on (Settings › Communication › SMS Messages): each message is paid, so switching SMS on for a test does not start texting every customer.

## Where is each one queued?
- `submit_order()` right after the `order.created` webhook (the order has its number): `pg_sms_order_received()`, reference `order:<id>:received`.
- `update_order()` (order screen, Workspace, API `ship`) per address that gained a tracking number and whose customer is told (the same `shipped` flag that keeps the e-mail back): `pg_sms_order_shipped()`, reference `order:<id>:shipped:<ship_to_id>`. One text per address: a number added later is deduped by the reference while the queue row is kept (sent rows: 7 days).
- `pg_shipment_delivered()` when it announces and `notify` is true: `pg_sms_order_delivered()`, reference `order:<id>:delivered:<ship_to_id>`.
- Not texted: marketplace orders (`mp_order_apply_tracking()`, delivery with `notify` false; the marketplace tells its buyer) and ShipWorks' direct insert.
- `expires_at` is two days after queueing: a queue held up longer drops stale order news.

## Why not inside `pg_shipment_record()`?
- It runs per number and has no notion of "the customer is told"; `update_order()` already decides that for the e-mail and the `order.shipped` event, so the text follows the same rule.

## Where are the templates?
- `connect_apps` row `sms_templates`, `options` = `{"events":{"<event>":{"on":bool,"text":""}}}`; not sealed (no secret), no schema step. Apart from the provider accounts, so forgetting or switching a provider keeps them.
- An empty text is the built-in one (`lang()`, so the site's language when queued); the built-in text sent back unchanged is stored empty.
- Fields `^^store^^` (ORGANIZATION_NAME), `^^name^^` (billing first name), `^^order_number^^`, `^^total^^` (received), `^^carrier^^`, `^^tracking_number^^` (all of the address's numbers in the save), `^^tracking_url^^` (first parcel's, `pg_shipment_view()`). A field the event lacks is refused on save; an empty value leaves no double space (`pg_sms_message_render()`). Templates at most 480 characters.

## What is still open?
- COD confirmation code at checkout and member OTP (Netgsm's OTP route or not): `plans/sms-and-iys.md` step 3b.

## How was it verified?
- `tests/sms_messages_test.php` (render, normalize, the card's decision). Not run on a sandbox: the hooks are one call each after paths the shipment and order tests already pin; Netgsm was not reached.

Related: docs/memory/platform/sms-outbox-queue.md, docs/memory/platform/sms-provider-layer.md, docs/memory/commerce/shipment-write-path.md, docs/memory/plans/sms-and-iys.md
