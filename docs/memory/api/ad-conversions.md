---
name: ad-conversions
description: Server-side ad events to Meta CAPI, GA4 MP and TikTok Events API: the start file, which file holds each part, the rules every change keeps, C1 to C12 with PRs, what is not built
---

## What is it, and who decided it?
- Owner (2026-11-20, poll 166): purchases and other events go from the server to Meta's Conversions API, the GA4 Measurement Protocol and TikTok's Events API; the shop pastes its own keys, with no app review and no middle server (`workflow/decisions-made-product-87.md`). One table, `ad_conversion_events` (`workflow/decisions-made-schema-35.md`).

## Which file holds which part?
- Table, consent reader, payment step, completion, the job's pass, sweep, privacy: `ad-conversions-queue.md` (#10732). Click ids: `ad-conversions-click-ids.md` (#10781).
- Meta: `ad-conversions-meta.md`, `meta-capi-facts.md` (#10812). GA4 and refunds: `ad-conversions-ga4.md`, `ga4-mp-facts.md` (#10866). TikTok: `ad-conversions-tiktok.md`, `tiktok-events-facts.md` (#10862).
- Lead, registration, add to cart, begin checkout, the receipt's `data-pg-event-id`: `ad-conversions-visitor-events.md` (#10868). Events card, alert, `GET /reports/ad-conversions`: `ad-conversions-log.md` (#10907).

## What does every change keep?
- No network call in a request: an event is a queue row, checkout never waits.
- Consent only through `pg_ad_conversion_consent_by()` (notice on, stored answer, the service's box), never `pg_consent_allows()`. Owner (2026-11-20, poll 175): notice off, no personal data unless the service's "Send personal data while the cookie notice is off" is ticked.
- Without consent nothing of the person; a purchase (GA4 also a refund) goes without the person only with "Send events without the person".
- Marketplace orders never; a counter sale only with the service's "Send counter sales" switch, never with the person (`ad-conversions-counter-sales.md`).
- Keys sealed in `config_text` and in `pg_reseal_registry()`; each page under the Visitors and privacy gate. No write route in the API.
- A service's request format, test mode and limits are read from its own document into its facts file, never from memory.
- Money in minor units until the body. A click id seen before consent waits in the session only.
- Architect (2026-11-20, message 10514): add to cart and begin checkout from the server, no new public endpoint, only with consent; the owner may turn it.

## Which feature is where?
- C1 the ids at the payment step: #10732, click ids #10781. C2 hashing at send: #10732. C3 purchase at completion: #10732; its counter sale switch: the PR of `ad-conversions-counter-sales.md`.
- C4 refund (GA4 only): #10866. C5 Lead and registration, C6 add to cart (20 a cart) and begin checkout, C7 the receipt's event id: #10868; the switches on GA4's and TikTok's pages: `ad-conversions-counter-sales.md`.
- C8 the three pages and the notice-off box: #10812, #10866, #10862. C9 without the person: same PRs. C10, C10b the table and queue: #10732. C11 log and alert, C12 the report: #10907.

## What is not built?
- A Lead or registration row has `order_id` 0, so the privacy erase (by order id) does not find it; its `ids` hold the hashed e-mail until sent or given up, and the row is swept at 30 days.

## What was left out?
- Google Ads' own conversion upload, Pinterest, Snapchat, a browser pixel field, the Meta catalogue (`plans/reverse-oauth-leftovers.md`): each a later yes of the owner.
