---
name: meta-capi-facts
description: Meta Conversions API as Meta's own documents say it (read 2026-11-20): address and version, body, required fields, hashing, 7-day limit, test code, answers and error codes
module: ad_conversions_meta
---

## Where were these read?
- 2026-11-20, developers.facebook.com: `marketing-api/conversions-api/using-the-api`, `parameters/server-event`, `…/customer-information-parameters`, `…/custom-data`, `graph-api/guides/error-handling`, the Ads Pixel Events reference. Read again before changing the sender.

## What is the request?
- `POST https://graph.facebook.com/{version}/{PIXEL_ID}/events`; the document's example uses `v27.0` (`PG_AD_CONVERSION_META_VERSION`).
- The token is the `access_token` parameter (query string in the text, a form field in its curl example); Pinegrap puts it in the JSON body, in no address or log.
- JSON body: `data` (array of server events, up to 1,000), `test_event_code` at the top level only for testing; Meta says to take it out of real events.

## Which fields does an event need?
- Required: `event_name`, `event_time` (Unix seconds, GMT), `user_data`, `action_source` (`website` here). `event_source_url` is required for website events.
- `event_id` is optional, recommended: equal to the browser pixel's `eventID` for dedup; an order number is suggested.
- `event_time` may be up to 7 days old; one older event makes Meta refuse the whole request.
- `custom_data`: `value` and `currency` (ISO 4217) required for Purchase; `contents` (`id`, `quantity`, `item_price`), `content_type` `product` or `product_group`, `order_id` as a string.

## How is the person sent?
- `em` and `ph`: SHA-256 required, string or list. E-mail trimmed, lower-cased; phone: digits with the country code, no symbols or leading zeros (`(650)555-1212` → `16505551212`).
- Never hashed: `client_ip_address`, `client_user_agent`, `fbc`, `fbp`.
- `fbc` is `fb.{subdomain_index}.{creation_time}.{fbclid}`, from the `_fbc` cookie or built from `fbclid`; `fbp` from the `_fbp` cookie.

## How does a store sale go?
- Read 2026-11-21, `documentation/ads-commerce/conversions-api/offline-events` and `…/parameters`: `action_source` `physical_store` for store events; website events need `client_user_agent`, `action_source`, `event_source_url`, others only `action_source`. Store events may come 62 days later, but the 7-day `event_time` limit holds.

## Does Meta take an event without the person?
- No. Read 2026-11-21, `documentation/ads-commerce/conversions-api/parameters/customer-information-parameters`: "You must provide at least one of the following user_data parameters"; so does the Payload Helper. "Non-web events require only action_source" lists event fields, not an exception.
- So counter sales, which carry no person, are not sent to Meta (#10960, `ad-conversions-counter-sales.md`), nor a purchase without the person (#10993, `ad-conversions-meta.md`).

## Would a fixed browser name make an event valid?
- No. Read 2026-11-21, `…/conversions-api/best-practices` (since v13.0): an event is invalid if its customer information parameters are only one of `ct`+`country`+`st`+`zp`+`ge`+`client_user_agent`, `db`+`client_user_agent`, `fn`+`ge`, `ln`+`ge`, "or a subset thereof". `client_user_agent` alone is such a subset.
- `customer-information-parameters`: `client_user_agent` is required for website events; `external_id` is in the list and in no invalid combination, so "Send a test event" adds a fixed one (#10993, `ad-conversions-meta.md`).

## What does Meta answer?
- 2xx for a valid body: `events_received` (integer), `messages` (list), `fbtrace_id`; a 200 does not prove the event shows in Events Manager.
- An invalid body: 4xx with `error`: `message`, `type`, `code`, `error_subcode`, `fbtrace_id`.
- Codes to wait and retry: 1, 2 (downtime), 4, 17 (throttling), 341, 368; 32, 613 rate limits too. 190: the token expired.

## What could not be checked?
- How Meta answers an empty `user_data`, one with only `client_user_agent`, or the test's fixed pair: refused, refused, taken, by the documents; no pixel to try.
