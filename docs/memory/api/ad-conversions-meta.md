---
name: ad-conversions-meta
description: Meta Conversions API (ad-conversions SC2): its Connections page and gate, the sealed token, the sender, how Meta's answers become a row's outcome, the test event, the registry row
module: ad_conversions_meta
---

## Where is it?
- `includes/fn/ad_conversions_meta.php`, loaded at the end of `ad_conversions.php`; tests `tests/ad_conversions_meta_test.php`. The request as Meta documents it: `meta-capi-facts.md`. The queue it sends from: `ad-conversions-queue.md`.

## What does its page hold?
- `connections.php?service=meta_capi`, under Visitors and privacy's gate (`connections_privacy_may_manage()`), for the page card and the writes `meta_capi_save` / `meta_capi_remove`.
- "Connection details": "Pixel ID" (digits), "Access token" (password field, never drawn back), "Test event code", "Events sent" ticks (Purchase only until SC5), "Without consent" › "Send events without the person" (C9), "Send personal data while the cookie notice is off" (C8) with its risk line. "Save" filled, "Send a test event" quiet (saves, then tries).
- Notice off and the box clear: a warning "The cookie notice is off: events go without the person" with a link to Cookie Consent.
- Ticking or clearing the box writes its own activity log line. "Disconnect" in Status empties the row; due rows are then given up by the job.

## Where are the settings kept?
- config_text `ad_conversions_meta_capi`, the same JSON `pg_ad_conversion_settings_parse()` reads, plus `pixel_id`, `token` (`pg_connect_seal(array('token' => …))`) and `test_event_code`. `connected` is true with a pixel and a token.
- The token is in `pg_reseal_registry()` (area `ad_conversions`, field `token`), so a key reset seals it again.

## How is a row sent?
- `pg_ad_conversion_send_meta_capi()`: one event per POST, the token in the JSON body, never in the address. `action_source` website, `event_source_url` the site's address, the event id the order reference.
- The person (C2) only when the row has it: `em`, `ph` hashed; `fbp`, `fbc`, address, browser name plain. The click id is read from the row's `ids` (`fbc`), never from `ad_click_ids.php`. Without the person `user_data` is an empty object.
- Refused without a call (400, given up): no pixel or token, an event Meta has no name for (refund), an event older than 7 days less 10 minutes.

## How are Meta's answers read?
- 2xx with `events_received` ≥ 1: sent. 2xx with none: refused.
- A Graph error: `error_user_msg` or `message`, the code and subcode, the `fbtrace_id` into `last_error`. Codes 1, 2, 4, 17, 32, 341, 613 answer 429 so the row is tried again; 190 (token) and other 400s are given up.
- The test code goes only with "Send a test event" (a 1.00 purchase without the person, id `pg-test-<time>`), never with a queued event.

## Registry?
- Row `meta_capi`, group Analytics and search, `place` `connections.php`, `found_at` `#settings/privacy/pgset-cookies`, state reader `pg_ad_conversion_meta_connected()`; transfer US, contact, order and payment, IP and cookie. `pg_connections_count()` is 79.
