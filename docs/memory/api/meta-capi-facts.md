---
name: meta-capi-facts
description: Meta Conversions API as Meta's own documents say it (read 2026-11-20): address and version, body, required fields, hashing, 7-day limit, test code, answers and error codes
module: ad_conversions_meta
---

## Where were these read?
- 2026-11-20 from developers.facebook.com: `marketing-api/conversions-api/using-the-api`, `parameters/server-event`, `parameters/customer-information-parameters`, `parameters/custom-data`, `graph-api/guides/error-handling`; the answer's fields from the Ads Pixel Events reference.
- Read them again before changing the sender (`ad-conversions.md`: never from memory).

## What is the request?
- `POST https://graph.facebook.com/{version}/{PIXEL_ID}/events`; the document's example uses `v27.0` (`PG_AD_CONVERSION_META_VERSION`).
- The access token goes as the `access_token` parameter: the document names the query string, its curl example a form field. Pinegrap puts it in the JSON body, so it is in no address or log.
- JSON body: `data` (array of server events, up to 1,000), `test_event_code` at the top level only for testing; Meta says to take it out of real events.

## Which fields does an event need?
- Required: `event_name`, `event_time` (Unix seconds, GMT), `user_data`, `action_source` (`website` here). `event_source_url` is required for website events.
- `event_id` is optional but recommended: it must equal the browser pixel's `eventID` for dedup; an order number is suggested.
- `event_time` may be up to 7 days old; one older event makes Meta refuse the whole request.
- `custom_data`: `value` and `currency` (ISO 4217) required for Purchase; `contents` (`id`, `quantity`, `item_price`), `content_type` `product` or `product_group`, `order_id` as a string.

## How is the person sent?
- `em` and `ph`: SHA-256 required, string or list. E-mail trimmed and lower-cased; phone digits only with the country code, symbols and leading zeros removed (`(650)555-1212` → `16505551212`).
- Never hashed: `client_ip_address`, `client_user_agent`, `fbc`, `fbp`.
- `fbc` is `fb.{subdomain_index}.{creation_time}.{fbclid}`, from the `_fbc` cookie or built from `fbclid`; `fbp` from the `_fbp` cookie.

## How does a store sale go?
- Read 2026-11-21 from developers.facebook.com `documentation/ads-commerce/conversions-api/offline-events` and `…/parameters`: `action_source` `physical_store` for every offline and store event; website events need `client_user_agent`, `action_source` and `event_source_url`, other events only `action_source`. Store events may be uploaded up to 62 days later, but the 7-day `event_time` limit still holds.

## What does Meta answer?
- 2xx for a valid body: `events_received` (integer), `messages` (list), `fbtrace_id`. A 200 does not prove the event shows in Events Manager.
- An invalid body: 4xx with `error`: `message`, `type`, `code`, `error_subcode`, `fbtrace_id`.
- Codes to wait and retry: 1, 2 (downtime), 4, 17 (throttling), 341, 368; 32 and 613 are rate limits too (rate limits guide). 190: the token expired, get a new one.

## What could not be checked?
- Whether Meta takes an event with an empty `user_data` (a purchase without the person, C9): no pixel to try against. A refusal gives the row up with Meta's words in `last_error`.
