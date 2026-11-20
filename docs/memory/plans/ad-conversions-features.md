---
name: ad-conversions-features
description: Plan ad-conversions, features C1 to C12: the buyer's ids and consent, the events, the three services, dedup, the ad_conversion_events table as queue and log, API
---

## What is kept from the visit?
- C1 When a consenting buyer reaches the payment step, a purchase row per connected service is written with status 0 and that service's ids (`_fbp`, `_fbc` or `fbclid`, the `_ga` client id, `ttclid` and `_ttp`, `gclid`), IP and browser name in `ids`; renewed at send. Consent taken back: `ids` NULL, `person` 0. Without consent no id is kept.
- C2 At send time e-mail and phone are normalised and hashed with SHA-256 as each service asks; the plain value never leaves the site.

## Which events go out?
- C3 Purchase, when a site order completes: value, currency, product codes, quantities, the order's reference as the event id. Marketplace orders never; counter sales with a setting, without the person.
- C4 Refund: GA4's refund event when a refund is recorded (`order_refunds`, a return's refund); the services without a refund event get none.
- C5 Lead for the forms the shop picks, CompleteRegistration when an account is opened.
- C6 Add to cart: `cart_action.php` calls one function that returns at once unless the service has the event ticked and the buyer consented, else writes one row; at most 20 a cart. Begin checkout: the request that first writes the purchase rows, once per order. Both only with consent; off by default.
- C7 Dedup: the event id is the one a browser pixel would send; the receipt page carries it in a data attribute (`data-pg-event-id`) so a pasted pixel can send the same id.

## Which services, and how are they set?
- C8 Three Connections pages (`connections.php?service=meta_capi`, `ga4_mp`, `tiktok_events`), each with its fields, "Save", "Send a test event" and Disconnect:
  - Meta: Pixel id and access token; the test event code field for Events Manager's test view.
  - GA4: the Measurement ID from the Google Analytics page, and an API secret; the test goes to the validation endpoint.
  - TikTok: Pixel code and access token; the test event code.
  - Which events each service gets: ticks on its page.
  - "Send personal data while the cookie notice is off": off by default, per service, in its `config_text` row, with a plain line under it: the buyer was not asked and the risk is the shop's. It acts only while the notice is off. Notice off and box clear: the page says "The cookie notice is off: events go without the person" and points to the box and the cookie consent setting. Ticking or clearing it writes an activity log line (who, which service).
- C9 Without consent: "Send events without the person" (amount and products only) for purchase and refund, off by default; at completion with no row, a row without the person is written. With the notice off, personal data (hashed e-mail and phone, ids, IP, browser name) goes only when the service's box (C8) is ticked.

## How does it stay reliable?
- C10 `ad_conversion_events`, one row per event and service; its columns and keys: `workflow/decisions-made-schema-35.md`. Status 0 waits for the order, 1 due, 2 sent, 3 refused or given up.
- C10b Queued at completion; the body built at send from the order (C2), never a plain e-mail or phone in the row. Its own pass in the general job with its own time budget; `api_webhook_backoff()` and `api_http_should_retry()`; a refused body (400) is not retried; a service that timed out has its other rows put off without a call. Rows over 30 days swept; `pg_privacy_erase_tables()` covers a person's orders' rows.
- C11 From the table, each page shows the last 50 events (time, event, order, sent or refused with the reason, with or without the person), and per day sent, failed and the share with the person. An operations alert when a service refused every event of a day.

## Who can read it from outside?
- C12 `GET /reports/ad-conversions`: per service the counts of the last 30 days and the last errors; an MCP tool by the route rule. No write route: keys are typed only on the panel.
