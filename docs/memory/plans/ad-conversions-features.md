---
name: ad-conversions-features
description: Plan ad-conversions, features C1 to C12: the buyer's ids and consent, the events, the three services, matching and dedup, the queue and log, API
---

## What is kept from the visit?
- C1 While the buyer is on the site (cart and checkout requests), the ids the services set or pass (`_fbp`, `_fbc` or `fbclid`, the `_ga` client id, `ttclid` and `_ttp`, `gclid`) and the marketing answer are kept for the order, where the architect decides. Without marketing consent no id is kept.
- C2 At send time e-mail and phone are normalised and hashed with SHA-256 as each service asks; the plain value never leaves the site.

## Which events go out?
- C3 Purchase, when a site order completes: value, currency, product codes, quantities, the order's reference as the event id. Marketplace orders never; counter sales with a setting, without the person.
- C4 Refund: GA4's refund event when a refund is recorded (`order_refunds`, a return's refund); the services without a refund event get none.
- C5 Lead for the forms the shop picks, CompleteRegistration when an account is opened.
- C6 Add to cart and begin checkout, per the architect's answer; off by default.
- C7 Dedup: the event id is the one a browser pixel would send; the receipt page carries it in a data attribute (`data-pg-event-id`) so a pasted pixel can send the same id.

## Which services, and how are they set?
- C8 Three Connections pages (`connections.php?service=meta_capi`, `ga4_mp`, `tiktok_events`), each with its fields, "Save", "Send a test event" and Disconnect:
  - Meta: Pixel id and access token; the test event code field for Events Manager's test view.
  - GA4: the Measurement ID from the Google Analytics page, and an API secret; the test goes to the validation endpoint.
  - TikTok: Pixel code and access token; the test event code.
  - Which events each service gets: ticks on its page.
- C9 Without consent: "Send events without the person" (amount and products only), off by default.

## How does it stay reliable?
- C10 One queue row per event and service; retries with the queue's ladder; a service's answer that refuses the body (400) is not retried. Nothing waits in checkout.
- C11 Each page shows the last 50 events (time, event, order, sent or refused with the reason, with or without the person), and per day sent, failed and the share with the person. An operations alert when a service refused every event of a day.

## Who can read it from outside?
- C12 `GET /reports/ad-conversions`: per service the counts of the last 30 days and the last errors; an MCP tool by the route rule. No write route: keys are typed only on the panel.
