---
name: ad-conversions-ga4
description: GA4 Measurement Protocol (#10866): its Connections page, Measurement ID from the Google Analytics page, sealed API secret, sender, refunds from order_refunds, test
module: ad_conversions_ga4
---

## Where is it?
- `includes/fn/ad_conversions_ga4.php`, loaded at the end of `ad_conversions.php`; tests `tests/ad_conversions_ga4_test.php`. The request as Google documents it: `ga4-mp-facts.md`. The queue: `ad-conversions-queue.md`.

## What does its page hold?
- `connections.php?service=ga4_mp`, under Visitors and privacy's gate (`connections_privacy_may_manage()`), for the card and the writes `ga4_mp_save` / `ga4_mp_remove`.
- "Measurement ID" read-only: the Google tag ID of the Google Analytics page when it starts with `G-`; otherwise a warning linking there. Not typed twice.
- "API secret" (password field, never drawn back), then the shared choices block (`ad-conversions-counter-sales.md`): "Events sent" switches for all six (`purchase`, `refund`, `add_to_cart`, `begin_checkout`, `sign_up`, `generate_lead`), "Forms sent as Lead", "Without consent" (Purchase only), "Counter sales", the notice-off switch; "Save", "Send a test event" (saves, then validates), "Disconnect" in Status.

## Where are the settings kept?
- config_text `ad_conversions_ga4_mp`: the queue's keys plus `secret` (`pg_connect_seal(array('secret' => …))`), in `pg_reseal_registry()`. `connected` is true with a secret; the state reader `pg_ad_conversion_ga4_connected()` also wants a `G-` Measurement ID.

## How is a row sent?
- `pg_ad_conversion_send_ga4_mp()`: one event per POST to `/mp/collect`. `transaction_id` (purchase and refund only) is the purchase's event id (the order reference, `data-pg-event-id` of C7); value is the order total, as Meta gets it. An event without a currency (Lead, sign-up) has no `value` or `currency`.
- With the person: the `_ga` client id from the row's `ids`, consent `GRANTED`, `ip_override` and `user_agent`. Without it (or no `_ga` cookie): a client id made from the transaction id (same for a purchase and its refunds), consent `DENIED`, nothing of the visitor.
- No `user_data`: the event carries Meta-normalised hashes, GA4 normalises otherwise and wants a `user_id`. Adding it needs GA4's own hashes in `pg_ad_conversion_event()`.
- Refused without a call (400, given up): no Measurement ID or secret, an event GA4 has no name for, an event older than 72 hours less 10 minutes.
- Answers: a 2xx is sent; 429 and 5xx retried; other statuses given up, as Google says not to resend.

## How do refunds go?
- `pg_ad_conversion_ga4_queue_refunds()` in `job.php` before the dispatch: with Refund ticked, each `order_refunds` row of the last 72 hours whose order's GA4 purchase was sent (status 2) gets a due row, event id `<reference>-refund-<refund id>`, value its `amount_cents`, no items, never the person. The unique key keeps it one row.
- It covers every writer of `order_refunds` (order page, a return's refund, store credit) without a call in `view_order.php`, `returns.php` or `store_credit_refunds.php`. A refund after the purchase row was swept (30 days) is not sent.

## The test?
- "Send a test event": a 1.00 purchase without the person to `/debug/mp/collect`; Google's validation messages shown as written. It does not check the secret.

## Registry?
- Row `ga4_mp`, group Analytics and search, `place` `connections.php`, `found_at` `#settings/privacy/pgset-cookies`; transfer US, order and payment, IP and cookie (no contact data).
