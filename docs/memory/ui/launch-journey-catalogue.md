---
name: launch-journey-catalogue
description: The launch journey's pure catalogue (SJ1a, PR #11160): purposes, steps per purpose, each step's rule over a facts array, blocker or warning, payment live per driver, the first week
module: launch_journey
---

## Where is it and what does it hold?
- `includes/fn/launch_journey.php`, loaded by `functions.php`; all pure, tests in `tests/launch_journey_test.php`. The card, the go-live check and the API (`launch-journey.md`) read it; none of them decides a step again.
- Purposes: shop, company, association, school, clinic, agency, blog, other (`pg_launch_journey_purposes()`); anything else is other (`pg_launch_journey_purpose()`).
- Steps (`pg_launch_journey_steps()`): every path starts with domain_ssl; a shop adds payment_live, a clinic booking_hours; a blog puts search_open and search_tools before mail; then mail, legal, search_open, search_tools, backups, cron, team. maintenance is a go-live check only, never a card step.

## What are the facts?
- One array the caller fills from the functions that already decide each answer; the file header lists every key and its source. A missing key makes the step unknown, and unknown never blocks.

## When does a step block, warn or neither?
- `pg_launch_journey_rule($key, $facts, $purpose)` answers state (done, todo, unknown), level (blocker, warning, none) and a reason code.
- Blockers: robots catch-all closed (`pg_robots_catch_all_closed()` over the robots text with its extras) or home page noindex; maintenance mode on; payment in test mode; no personal-data text on a shop or where `collects_data`; mail not sending; a scheduled task that ran and stopped.
- Warnings: no HTTPS or a certificate expired or expiring; mail SPF, DKIM or DMARC not ok; no consent or a shop's legal documents off; no Search Console; no online payment way or its mode unknown; no booking hours; no backup or an old one; scheduled tasks never ran; broken internal links or the SEO check never run (`broken_links`, go-live check only, `ui/launch-journey-go-live.md`).
- Neither (a step, not asked by the go-live check): Analytics not connected while Search Console is; no other user signed in yet (team).

## How is payment live judged per driver?
- `pg_launch_journey_payment_live($driver, $settings, $gateway_mode)`: Stripe by its keys' prefix (`pk_`, `sk_`, `rk_` then `test_` or `live_`; one test key is test, no key unknown); PayTR `mode` test, PayPal `mode` sandbox; iyzico and the classic gateways by `ECOMMERCE_PAYMENT_GATEWAY_MODE`.

## What do the card and the go-live check get?
- `pg_launch_journey_state()`: rows blockers first, then warnings, then the rest; done, total, status (blocked, in_progress, ready), finished when every step is done or hidden. A hidden step stays while it blocks and is never counted done.
- `pg_launch_journey_golive_check()`: blockers (maintenance first) and warnings; a hidden step's warning is left out, a blocker never.
- `pg_launch_journey_hidden_clean()` keeps stored hidden keys to card steps, once each.

## How long is the first week?
- `pg_launch_journey_first_week($live_at, $now)`: seven days (`PG_LAUNCH_JOURNEY_FIRST_WEEK`) from the go-live time; open, day 1 to 7, ends_at, left; closed before going live and from the week's last second on.
