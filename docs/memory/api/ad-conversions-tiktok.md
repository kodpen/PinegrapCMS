---
name: ad-conversions-tiktok
description: TikTok Events API (ad-conversions SC4): its Connections page and gate, the sealed token, the sender, the E.164 phone hash, how TikTok's answers become a row's outcome, the test event, the registry row
module: ad_conversions_tiktok
---

## Where is it?
- `includes/fn/ad_conversions_tiktok.php`, loaded at the end of `ad_conversions.php`; tests `tests/ad_conversions_tiktok_test.php`. The request as TikTok documents it: `tiktok-events-facts.md`. Built on Meta's shape (`ad-conversions-meta.md`).

## What does its page hold?
- `connections.php?service=tiktok_events`, under Visitors and privacy's gate (`connections_privacy_may_manage()`), for the card and the writes `tiktok_events_save` / `tiktok_events_remove`.
- "Connection details": "Pixel code" (capital letters and digits, upper-cased on save), "Access token" (password field, never drawn back), "Test event code", "Events sent" (Purchase only until SC5), "Without consent" › "Send events without the person" (C9), "Send personal data while the cookie notice is off" (C8) with its risk line and, notice off and box clear, the warning with the Cookie Consent link. "Save" filled, "Send a test event" quiet.
- Ticking or clearing the box writes its own activity log line; "Disconnect" in Status empties the row.

## Where are the settings kept?
- config_text `ad_conversions_tiktok_events`: the queue's keys plus `pixel_code`, `token` (`pg_connect_seal()`), `test_event_code`. In `pg_reseal_registry()` (area `ad_conversions`, field `token`).

## How is a row sent?
- `pg_ad_conversion_send_tiktok_events()`: one event per POST, the token in the `Access-Token` header. `page.url` the site's address, the event id the order reference.
- The person only when the row has it: `email` hashed; `phone` hashed by TikTok's rule (E.164 with +) from the order found by `reference_code` (the purchase's event id), because the shared event's hash has no +. `ttp`, `ttclid`, `ip`, `user_agent` plain from the row's `ids`; never from `ad_click_ids.php`. Without the person `user` is an empty object.
- Refused without a call (400, given up): no pixel code or token, an event TikTok has no name for (refund).

## How are TikTok's answers read?
- HTTP 2xx with `code` 0: sent. A 2xx with code 40100 or 50000 and up answers 429 (tried again); any other code, or no code, is a refusal (400). An HTTP error keeps its status. `last_error`: TikTok's message, the code and `request_id`.
- The test code goes only with "Send a test event" (Meta's 1.00 test purchase without the person), never with a queued event.

## Registry?
- Row `tiktok_events`, group Analytics and search, `place` `connections.php`, `found_at` `#settings/privacy/pgset-cookies`, reader `pg_ad_conversion_tiktok_connected()`; transfer SG (TikTok Pte. Ltd., the contracting company outside the US and EEA; the shop checks its own), contact, order and payment, IP and cookie. `pg_connections_count()` is 80.
