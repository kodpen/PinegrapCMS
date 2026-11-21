---
name: ad-conversions
description: Server-side ad events to Meta CAPI, GA4 MP and TikTok Events API: the start file, which file holds each part, the rules every change keeps, C1 to C12 with PRs, what is not built
---

## What is it, and who decided it?
- Owner (2026-11-20, poll 166): purchases and other events go from the server to Meta's Conversions API, the GA4 Measurement Protocol and TikTok's Events API; the shop pastes its own keys, with no app review and no middle server (`workflow/decisions-made-product-87.md`). One table, `ad_conversion_events` (`workflow/decisions-made-schema-35.md`).

## Which file holds which part?
- Table, consent reader, payment step, completion, the job's pass, sweep: `ad-conversions-queue.md` (#10732). Erase and export: `ad-conversions-privacy.md`. Click ids: `ad-conversions-click-ids.md` (#10781).
- Meta: `ad-conversions-meta.md`, `meta-capi-facts.md` (#10812). GA4 and refunds: `ad-conversions-ga4.md`, `ga4-mp-facts.md` (#10866). TikTok: `ad-conversions-tiktok.md`, `tiktok-events-facts.md` (#10862).
- Lead, registration, add to cart, begin checkout, the receipt's `data-pg-event-id`: `ad-conversions-visitor-events.md` (#10868). Events card, alert, `GET /reports/ad-conversions`: `ad-conversions-log.md` (#10907).

## What does every change keep?
- No network call in a request: an event is a queue row, checkout never waits.
- Consent only through `pg_ad_conversion_consent_by()` (notice on, stored answer, the service's box), never `pg_consent_allows()`. Owner (2026-11-20, poll 175): notice off, no personal data unless the service's "Send personal data while the cookie notice is off" is ticked.
- Without consent nothing of the person; a purchase (GA4 also a refund) goes without the person only with "Send events without the person".
- Marketplace orders never; counter sales never while they have no setting.
- Keys sealed in `config_text` and in `pg_reseal_registry()`; each page under the Visitors and privacy gate. No write route in the API.
- A service's request format, test mode and limits are read from its own document into its facts file, never from memory.
- Money in minor units until the body. A click id seen before consent waits in the session only.
- Architect (2026-11-20, message 10514): add to cart and begin checkout from the server, no new public endpoint, only with consent; the owner may turn it.

## Which feature is where?
- C1 the ids at the payment step: #10732, click ids #10781. C2 hashing at send: #10732. C3 purchase at completion: #10732.
- C4 refund (GA4 only): #10866. C5 Lead and registration, C6 add to cart (20 a cart) and begin checkout, C7 the receipt's event id: #10868.
- C8 the three pages and the notice-off box: #10812, #10866, #10862. C9 without the person: same PRs. C10, C10b the table and queue: #10732. C11 log and alert, C12 the report: #10907.

## What is not built?
- C3's counter sale setting: `local` orders are never sent.
- C5, C6 on GA4 and TikTok: their pages tick only Purchase (GA4 also Refund). Their senders already name `generate_lead`, `sign_up`, `add_to_cart`, `begin_checkout` (TikTok: Lead, CompleteRegistration, AddToCart, InitiateCheckout), but only Meta's page writes those ticks.

## What was left out?
- Google Ads' own conversion upload, Pinterest, Snapchat, a browser pixel field, the Meta catalogue (`plans/reverse-oauth-leftovers.md`): each a later yes of the owner.
