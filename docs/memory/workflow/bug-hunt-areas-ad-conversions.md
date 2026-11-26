---
name: bug-hunt-areas-ad-conversions
description: The bug hunt in the ad conversions (agent channel request 3045, 2026-11-26): what was fixed, what was read clean against the consent rule, what is left
---

## Which area was it?
- Ad conversions (#10732 to #10993): `includes/fn/ad_conversions.php`, `_meta.php`, `_ga4.php`, `_tiktok.php`, `_log.php`, `ad_click_ids.php`, read against their own consent rule (`api/ad-conversions.md`). The scheduled-tasks hunt had read only the queue's row claim.

## What was fixed?
- Marketing refused after an order's row was due (status 1): the row lost the person but stayed due, so GA4 and TikTok got the purchase although "Send events without the person" was off, and add to cart and begin checkout went out after the refusal.
- Now `pg_ad_conversion_withdraw_plan()` gives such a row up ("No consent"); a due purchase stays due without the person only where that switch is on (never Meta). A waiting row (0) only loses its ids (`api/ad-conversions-queue.md`).
- Begin checkout went with value 0: it read `orders.total`, which an incomplete order gets only when the payment starts or the order completes, after the payment step. It now carries the cart lines' price times quantity (`pg_ad_conversion_begin_checkout_detail()`, `api/ad-conversions-visitor-events.md`).

## What was read and found clean?
- Consent: every writer reads `pg_ad_conversion_consent_by()` with its own service's notice-off box; `pg_consent_allows()` is not used. Completion and the counter sale read no visitor data.
- Events: the purchase event id is the reference the receipt's `data-pg-event-id` carries; the unique key and the completion lock keep one row; the claim keeps one send. Meta never gets a row without the person (the sender refuses it without a call). A GA4 refund is queued only for a purchase GA4 received (status 2).
- Value: amounts stay in minor units until `pg_ad_conversion_decimal()`, built from integers. The site keeps every currency in hundredths, so a currency without decimals is still right.
- The pass: no network call in a request; 408, 429, 5xx and no answer retried up to six times with the webhook backoff, other refusals given up once; the 30-day sweep in batches; sent and given-up rows drop `ids`.
- Keys: Meta's token goes in the body, TikTok's in a header, GA4's secret in the query only; `last_error` holds the services' messages, never the address; the Events card and the report never select `ids`.
- Click ids: bound to 500 safe characters, dropped not cut; held in the session only until consent; cleared on refusal.

## What is left?
- A Lead or sign-up row (order id 0) keeps its ids if marketing is refused before the next job pass sends it: the refusal finds rows only by the session's order ids. Finding them needs a session key, which ends the anonymous page cache for that visit.
- The Events card counts a row given up for "No consent" as failed, so a day where every buyer refused marketing raises "refused all events" on the service. Counting is a product choice (`api/ad-conversions-log.md`), not changed.
- Add to cart is sent only from the cart's quick add (`cart_action.php`), not from the other add-to-cart paths.
