---
name: tiktok-events-facts
description: TikTok Events API 2.0 as TikTok's own documents say it (read 2026-11-20): address, Access-Token header, body, hashing (phone in E.164 with +), test code, answers, what could not be checked
module: ad_conversions_tiktok
---

## Where were these read?
- 2026-11-20 from business-api.tiktok.com: `portal/docs/events-api-2.0/v1.3`, `report-app-web-offline-or-crm-events/v1.3`, `event-deduplication/v1.3`, `events-api-v1-web-set-up-external-id/v1.3`; ads.tiktok.com help: `standard-events-parameters`, the Tealium and Hightouch Events API guides (test code).
- The portal draws its pages by script; they were read through search excerpts of those pages, not as whole pages. Read them again before changing the sender (`ad-conversions.md`: never from memory).

## What is the request?
- `POST https://business-api.tiktok.com/open_api/v1.3/event/track/` (`PG_AD_CONVERSION_TIKTOK_URL`), the recommended endpoint for web, app, offline and CRM events. Events API 1.0 (`pixel/track`) is the old one.
- Header `Access-Token: <token>`, `Content-Type: application/json`. Pinegrap sends the token nowhere else.
- Body: `event_source` (`web`), `event_source_id` (the pixel code), `data` (up to 1,000 events; more and the whole request is refused), `test_event_code` at the top level for a test only.

## Which fields does an event need?
- `event` (a standard name: `Purchase`, `AddToCart`, `InitiateCheckout`, `Lead`, `CompleteRegistration`; TikTok recommends Purchase where placing an order and paying are one), `event_time` (Unix seconds, UTC+0, required), `event_id` (required for dedup with the browser pixel; same pixel, event and id within 48 hours keeps the first).
- `page` (`url`) is required for web events. `properties`: `value`, `currency`, `contents` (`content_id`, `quantity`, `price` of one item), `content_type`, `order_id`.

## How is the person sent?
- `user.email`: trimmed, lower-cased, SHA-256. `user.phone`: E.164 with the + and country code, then SHA-256 (`(+1)2133734253` → `+12133734253`). Not Meta's digits alone, so the sender hashes the order's phone itself (`ad-conversions-tiktok.md`).
- Plain: `ttp` (the `_ttp` cookie), `ttclid` (web only), `ip`, `user_agent`. `external_id` would be SHA-256; Pinegrap sends none.

## What does TikTok answer?
- HTTP 200 with `code` 0, `message`, `request_id`, `data` for a taken request. A code other than 0 is an error, its words in `message`; a failed request may also be a 4xx or 5xx.

## What could not be checked?
- The return codes appendix: 40100 (too many requests) and codes of 50000 and up (TikTok's fault) are read as "try again" from TikTok's general API codes, not from the page; any other code is given up.
- How old an event may be: no limit was found, so no age refusal is made.
- No pixel to try against: the test event's acceptance was not seen.
- Whether an event with an empty `user` is taken: read 2026-11-21, ads.tiktok.com `how-to-set-up-matching-events-with-events-api` and `standard-events-parameters` present the user keys as improving the match, not as required; no TikTok page found asks for one. So TikTok keeps "Send events without the person" (#10993 checked it).
