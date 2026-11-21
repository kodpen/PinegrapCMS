---
name: ga4-mp-facts
description: GA4 Measurement Protocol as Google's own documents say it (read 2026-11-20): addresses, secret, body, client id, consent, 72-hour limit, user data hashing, validation endpoint, answers
module: ad_conversions_ga4
---

## Where were these read?
- 2026-11-20 from developers.google.com: `analytics/devguides/collection/protocol/ga4/reference` (gtag.js), `…/ga4/sending-events`, `…/ga4/validating-events`, `…/ga4/reference/events` (purchase, refund) and `analytics/devguides/collection/ga4/uid-data`.
- Read them again before changing the sender (`ad-conversions.md`: never from memory).

## What is the request?
- `POST https://www.google-analytics.com/mp/collect?measurement_id=G-…&api_secret=…`; `region1.google-analytics.com` for EU collection (not used). POST only; the body under 130 kB.
- `api_secret`: Admin › Data Streams › your stream › Measurement Protocol › Create; the only place Google takes it is the query string.
- Body: `client_id` (required for web streams), optional `user_id`, `timestamp_micros`, `user_properties`, `user_data`, `consent`, `ip_override`, `user_agent`, `validation_behavior`; `events` (required, up to 25), each `name` and `params`.

## What does a client id look like?
- Two positive numbers joined by a period (recommended), or the full `_ga` cookie value. The document says nothing of an event without the visitor; it is required all the same.

## What limits hold?
- Events backdated up to 72 hours; older ones are moved to 72 hours ago (`RELAXED`) or refused (`ENFORCE_RECOMMENDATIONS`). Joined with tag events when received within 48 hours.
- Event and parameter names 40 characters, letters, digits, underscores, starting with a letter; 25 parameters an event; values 100 characters.

## Which parameters do purchase and refund take?
- purchase: `transaction_id` and `items` required; `value` with `currency` (value = sum of price × quantity, without shipping and tax). Items: `item_id` or `item_name`, `price`, `quantity` (default 1).
- refund: `transaction_id` required; `value` and `currency` as for purchase; `items` optional, recommended for item-level refund figures.

## How is consent sent?
- `consent`: `ad_user_data` and `ad_personalization`, each `GRANTED` or `DENIED`; left out, GA uses the consent of the client's online events.

## How is user data hashed?
- `user_data.sha256_email_address[]`, `sha256_phone_number[]`, hex SHA-256; `user_id` recommended whenever `user_data` is sent.
- E-mail: trimmed, lower-cased, dots before `@` removed for gmail.com and googlemail.com. Phone: E.164, digits with a `+` prefix. Both differ from Meta's (`meta-capi-facts.md`).

## What does Google answer?
- `/mp/collect`: a 2xx when the request was received, also for a malformed or unused body. Not a 2xx: correct the request, do not retry it as it was.
- `/debug/mp/collect` (validation): `{"validationMessages": []}` when valid, else messages with `fieldPath`, `description`, `validationCode` (`NAME_INVALID`, `VALUE_REQUIRED`, …). It does not check `api_secret`; its events never reach reports.
