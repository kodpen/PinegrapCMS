---
name: marketplace-repricer-robot
description: The repricer's robot pass (RP4): Win and Grow while winning, the floor and cap, the day's limits, Try first, the log, the listing state, the two operations alerts
module: marketplace_repricer
---

## When does the robot run?
- `mp_repricer_run()` (`includes/fn/marketplace_repricer.php`) right after each buybox batch is stored, in `mp_sync_buybox_account()` (`sync.php`), on what was just read: hourly per listing.
- Only for an account with its own `marketplace_price_rules` row (`robot` 1 Try first, 2 sending); no row (robot 0) does nothing. n11 has no buybox read, so no robot.
- A listing with a typed own price is never moved (R2); Fixed listings only get their state.

## What does each strategy do?
- Win: another seller holds the buybox: its price less one step (`mp_pricing_step()` from the buybox price), never over the rules' price, at the floor at least (reason `floor`, else `lost`). Losing while already cheaper: never raised. Ours against others: kept. Ours alone: back to the rules' price (`raised`).
- Grow while winning: ours: one step up (from the current price), at most `grow_cap` over the rules' price, one raise in 24 h, never to a price that lost within 7 days (`lost_price`, `lost_at`). Lost: one step back (`back`), never under the rules' price, the price it lost at kept.
- No floor (no cost and none typed) or no read (rank 0): nothing (`mp_repricer_target()`).

## What are the day's limits?
- From the listing's `marketplace_price_log` rows of the last 24 h, Try first rows included: at most 4 changes, and at most 10% from the price before the first (`mp_repricer_day()`, `mp_repricer_limit()`).
- The limit only holds a move short: when the rules' price or the floor moved the price outside the day's 10%, a cut goes no deeper than wanted and a raise is never turned into a cut (bug hunt, #11768; before, a Win cut was clamped into the window, under the floor). A move cut short of the floor by the 10% is logged `lost`, not `floor`.

## How does a price go out?
- Sending (robot 2) and `push_price` on: the change sets `marketplace_price_state.robot_price` and queues the listing (`mp_matches_send()`); the log row has `sent` 1.
- `mp_pricing_product_price()` sends the robot price only while robot is 2, the strategy is Win or Grow and a floor exists (`mp_repricer_send_price()`), held between the floor and the cap as they are at send time, so a site price or cost that moved moves it (R9). The row carries `price_robot`; the price history source is `repricer` (`mp_sync_record_channel_prices()`).
- Try first (robot 1): the log row with `sent` 0 and the would-be `robot_price`, which the next pass walks from; nothing is queued and nothing reads it at send time.

## What is kept per listing and in the log?
- `marketplace_price_state.state` (`mp_repricer_state()`): 0 not read, 1 winning, 2 losing, 3 losing at the floor, 4 no floor; written for every listing the pass reads. `changed_at` on a change.
- Log row: old and new price, floor, buybox price, currency, reason (`lost`, `floor`, `raised`, `back`; `mp_repricer_reasons()`; architect, channel message 11220: plain strings, RP3b maps its own labels), sent, time; swept after 90 days by `mp_sync_sweep()`.

## Which alerts?
- `repricer_losing`: more than half the Win listings read in one account's pass are held by another seller. `buybox_failing`: the read failed 3 passes in a row (`buybox_failures` per account in the operations alerts' state file; a good read clears it). Both once a day per account (subject `account_<id>`), no `group` (`mp_repricer_alerts()`).

## What is tested?
- `tests/marketplace_repricer_test.php`: each strategy at and over the floor, the cap, a lost buybox and the 7-day wait, the limits, the send price, the state, the alerts and the log row.

Related: docs/memory/commerce/marketplace-buybox-read.md, docs/memory/commerce/marketplace-channel-price.md, docs/memory/commerce/marketplace-pricing-tab.md
