---
name: bug-hunt-areas-launch-journey
description: The bug hunt of the site set-up journey (SJ1a to SJ5, 2026.11.0): the facts reader, the set-up card, "Go live", First week and Start here, the agency summary
---

## Which area was hunted?
- Architect (2026-11-26): the site set-up journey, what it decides. The look, the rights and GET /launch were earlier hunts'.
- Read: `includes/fn/launch_journey.php`, `launch_journey_site.php`, `launch_journey_path.php`, `launch_summary.php` and the root `launch_summary.php`, the cards `launch.php`, `firstweek.php`, `starthere.php`.

## What did the hunt fix? (#11807)
- Mail step never done through a sending service: DKIM there is `unknown`/`service` (the service signs), so "E-mail sending" stayed "Worth a look" for good. Now `pg_launch_journey_mail_facts()` leaves that record out; a failed lookup stays a warning.
- PayPal Express Checkout was judged by the card gateway's mode; its own sandbox switch (`ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE`) now decides (`pg_launch_journey_payment_classic()`), so sandbox PayPal beside a live card is a blocker.
- The classic Stripe gateway (`Stripe`, not PaymentIntents) fell into the Stripe driver's rule with no keys: always "could not be read". Now its `ECOMMERCE_STRIPE_API_KEY` prefix decides.
- PayPal and PayTR drivers whose Mode was never saved read "could not be read"; the driver runs on the field default (live). Now `pg_launch_journey_payment_driver_settings()` fills defaults as `pg_payment_setting()` does.
- "Go live" wrote maintenance mode and the robots text in two statements: a failure after the first left the site half open with no "went live" line. Now one guarded UPDATE (`pg_launch_journey_golive_query()`).
- "Go live" pressed twice (double click) logged the changes and "The site went live." twice. The guarded statement changes no row the second time, and `pg_launch_journey_golive_log()` writes nothing for a press that changed nothing and found `live_at` kept.
- The `launch_journey` row (card, `live_at`, summary link) was read-change-written without a guard: a card closing itself could drop a summary link made in the same moment. Now `pg_launch_journey_row_change()` claims the row (`pg_config_text_claim()`) and retries on the newer value.
- Tests: `tests/launch_journey_facts_test.php`.

## What was left for a decision?
- Asked the architect (2026-11-26): the card closes itself when every card step is done or hidden while maintenance is still on, taking "Go live" with it; "Hide set-up" keeps a later blocker off the dashboard; a step whose module is off (Shop with the shop off, Clinic without bookings) stays "Could not be checked" and counts in "N of M". Unchanged until answered.

## What was read clean?
- The rules against their deciding functions: HTTPS, certificate, legal, robots catch-all and `pg_launch_journey_robots_opened()` (same group reading, comments and owner lines kept), home noindex, connections, backups, cron, team, booking hours, broken links.
- Purpose change after hiding: hidden keys of steps the new purpose lacks are harmless. First week: `live_at` in the future or past day 7 closes the card; counts start at `live_at`. Start here and hint keys (`pg_tour_list_marked()` keeps a hint once, catalogue screens only).
- Agency summary: HMAC token, hash compared with `hash_equals`; withdrawn, expired and cut links each get their own page; an answer after withdraw is refused before the POST is read; an unchanged answer writes nothing; answer kept at 5,000 characters and drawn with textContent; the `launch_answer` bell at most once an hour per line.

## What was not run?
- No database: "Go live" and the row claim were tried through their pure parts.
