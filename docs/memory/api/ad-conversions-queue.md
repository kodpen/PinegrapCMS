---
name: ad-conversions-queue
description: ad_conversion_events (11.0.254): the three-input consent reader, status 0 rows at the payment step, queueing at completion, the job's pass, sweep, privacy
module: ad_conversions
---

## Where is it?
- `includes/fn/ad_conversions.php`; table `ad_conversion_events` (2026.11.0, 11.0.254; columns and keys: `workflow/decisions-made-schema-35.md`). Tests: `tests/ad_conversions_test.php`.
- One row per event and service, unique on (`service`, `event_id`). Services: `meta_capi`, `ga4_mp`, `tiktok_events`. A purchase's `event_id` is the order's `reference_code` (`pg_ad_conversion_purchase_event_id()`), the id a browser pixel can send too.

## How is consent read?
- `pg_ad_conversion_consent_by($notice_on, $choices, $box)`, pure, never `pg_consent_allows()` (which says yes while the notice is off). Notice on: only a stored yes to marketing. Notice off: only the service's "Send personal data while the cookie notice is off" box; the stored answer is not read.

## What does each moment write?
- Payment step (`pg_ad_conversion_payment_step()`, `submit_order.php`: before the redirect to the bank or service page, and before `pg_order_complete()`): with consent a status 0 row with that service's ids in `ids` (`_fbp`, `_fbc`; the `_ga` client id; `_ttp`; address, browser name), renewed while status 0. Without consent no id is kept and a row kept earlier gets `ids` NULL, `person` 0.
- Completion (`pg_ad_conversion_order_completed()`, called in `pg_order_complete()` after its lock, so a second completion never reaches it; `INSERT IGNORE` on the unique key as well): status 0 with the person → due (1) with `detail` (value in minor units, currency, order number, time, lines: SKU, quantity, price).
- At completion, no row or a row without the person → a row without the person only when the service's `without_person` lists `purchase`, else given up (3, "No consent"). It reads no cookie: a payment service's message completes an order without the visitor.
- Consent taken back (`consent_answer.php` → `pg_ad_conversion_consent_changed()`): marketing refused clears `ids` and `person` on the cart's and the just-completed order's rows with status 0 or 1.
- Marketplace orders (`type = 'marketplace'`) are never sent; counter sales (`local`) not until their setting exists.

## Where do the settings live?
- `config_text` row `ad_conversions_<service>`, JSON: `connected`, `events` (missing → `purchase`), `person_notice_off` (the box), `without_person` (events sent without the person). Nothing writes them before SC2's Connections pages, so SC1 queues nothing on a live site.

## How is a row sent?
- `pg_ad_conversion_dispatch(10)` in `job.php`, apart from the webhook queue: sweep once a day (rows over 30 days, any status), then up to 50 due rows. Each row is claimed (`next_attempt_at` moved on), its event built at send (`pg_ad_conversion_event()`: amounts to decimals, e-mail and phone normalised and SHA-256 hashed then, ids only with the person) and handed to `pg_ad_conversion_send_<service>($event, $settings)` when that function exists; a service without a sender leaves its rows due.
- Outcome (`pg_ad_conversion_after_attempt()`): sent → 2, `ids` NULL; a refusal the service meant (400) or the last of `api_webhook_max_attempts()` → 3, `ids` NULL; else retried after `api_webhook_backoff()`. A service that did not answer (status 0) has its other rows left for the next pass. A row whose service was disconnected is given up.
- A sender answers `array('ok', 'status', 'error')` as `api_http_post()` does.

## Privacy?
- `pg_privacy_erase_tables()`: `ad_conversion_events` found by `order_id` → `order_ids`, deleted. Export section `ad_conversions` ("Ad service events") after `consents`.

## Click ids?
- The landing address's `fbclid`, `gclid`, `ttclid` join a consenting buyer's `ids` at the payment step: `ad-conversions-click-ids.md`.
