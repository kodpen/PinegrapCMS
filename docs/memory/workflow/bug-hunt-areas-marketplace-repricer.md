---
name: bug-hunt-areas-marketplace-repricer
description: The bug hunt in the marketplace repricer RP1 to RP5 (architect, 2026-11-26, request 3034): what was fixed, what was read clean, what is left
---

## Which area was it?
- The marketplace repricer: `includes/fn/marketplace_repricer.php` (robot pass), `includes/fn/marketplace_pricing.php` (rules, rounding, floor), `marketplace_pricing_settings.php` (Pricing tab, listing row), `marketplace_repricing.php` (Repricing view, API), the price part of `mp_product_row()` and the buybox read in `sync.php`.

## What was fixed? (#11768)
- Day limit: `mp_repricer_limit()` clamped the wanted price into the day's 10% window around the day's first price. When the site price or the cost had moved the price outside it, a Win cut went far deeper than wanted (stored under the floor, logged so; the send clamped it to the floor) and a Grow raise became a cut. The limit now only holds a move short.
- Pricing tab: switching Try first to or from sending changes every robot listing's sent price, but `mp_pricing_settings_changed()` ignored the switch, so nothing was queued and the robot's last prices stayed on the marketplace. The switch now counts.
- Listing row and API: Win or Grow while winning set back to Fixed stopped the robot's price but queued only on an own-price change. `mp_pricing_listing_moves()` now queues a strategy change while a robot price exists.
- Tests: `tests/marketplace_repricer_test.php`, `tests/marketplace_pricing_settings_test.php`.

## What was read and found clean?
- Floor: no cost and none typed is no floor, no robot (`mp_repricer_target()`, `mp_repricer_send_price()`); a typed 0 is none; the rules' price under the floor is lifted with its ending; the send price is held between the floor and the cap at send time.
- Minor units: percents through `pg_percent_of()`, typed figures `round()`ed, the buybox price `round()`ed from Trendyol; a rule's percent is taken once on the site price; the drivers send `price` as sale and list price.
- Buybox: an unanswered, refused or rank-less read stores rank 0 and the robot holds; a buybox price of 0 holds; rank over 1 is always another seller; no Pricing row (robot 0) does nothing.
- Prices leave only through `mp_matches_send()`, once per batch; history rows only from a send, none in Try first; Manager and up with the CSRF token on both screens, `products:write` on the API.

## What is left?
- The `repricer_losing` alert counts Win listings with no read, an own price or no floor in its total, which makes it rarer than "half the Win listings"; not changed.
- Prices are in the site currency against Trendyol's lira, as every marketplace send; a shop in another currency is outside this area.
