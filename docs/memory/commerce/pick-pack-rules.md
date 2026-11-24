---
name: pick-pack-rules
description: Pick and pack's pure rules (includes/fn/pick_pack.php): shelf address sort, wave rule filter and ship-by day, cancelled order leaving a wave, pick list totals, scan verdict codes, pack state
module: pick_pack
---

## What does `includes/fn/pick_pack.php` hold?
- Only pure functions: no query, no lang text. Callers hand in the rows they read and store what comes back. The feature's start file: `pick-pack.md`.
- Loaded by `functions.php` through `includes/fn/pick_waves.php` (the wave screens' module, `pick-pack-waves.md`). The tests require it themselves.

## How are shelf addresses sorted?
- `pg_pick_bin_compare()`: parts are runs of digits or letters, other characters only separate; numbers by value ("A-3" before "A-10", "03" = "3"), letters without case, a number before a letter, a shorter address first.
- `pg_pick_sort_lines()`: an empty address last; ties and the empty ones by product code (natural order), then product id.

## What does the wave rule let in?
- `pg_pick_wave_rule_match()`: paid, not cancelled, not shipped (a tracking number or a shipped unit), no packed pack, in no open wave; then the rule's filters (`carrier`, `marketplace`, `location_id`, `ship_by_until`, `single_item` = exactly one unit). An empty filter lets all through.
- `pg_pick_wave_rule_filter()`: oldest ship-by day first, unknown day last, then order and address id; at most `PG_PICK_WAVE_MAX` (200).
- `pg_pick_ship_by()`: the address's ship date, else the paid day plus handling days, as the day's start.
- `pg_pick_wave_leavers()`: ship_to_ids of rows not yet removed whose order is cancelled; the caller sets `removed_at`.
- `pg_pick_wave_rule_clean()`: the Make a wave drawer's fields to a rule; ship-by `today` (due today or late) or `tomorrow`; an unknown value lets all through; carrier at most 20 characters.

## What do the pick list's ticks follow?
- `pg_pick_line_key()` is `product:location`; `pg_pick_picked_toggle()` keeps each key once, sorted; `pg_pick_list_ticked()` puts ticked lines after the rest (shelf order kept) and counts only keys of lines on the list.
- `pg_pick_wave_status_after()`: every line ticked is Picked (`PG_WAVE_PICKED`), an untick Open again, Closed stays; an empty list is never Picked.
- `pg_pick_ship_by_tone()`: `late` before today, `today`, else ''. `pg_pick_single_item_address()` (K7): of the addresses wanting the scanned product and not done, oldest ship-by (unknown last), then order, then address id.

## What does the pick list total?
- `pg_pick_list_totals()`: one line per product and location, units summed, `addresses` counts distinct ship_to_ids; zero-unit lines skipped; shelf order.

## Which verdicts can a scan get?
- `pg_pack_scan_verdict()` returns codes, checked in this order: `not_found`, `binned`, `good` (first line with units left), `too_many` (on the address, all scanned), `wrong_variant` (same non-zero variant group; carries `options` and `wanted_options` so the screen names both), `wrong_product`.
- `pg_pack_apply_scan()`: a good scan adds one to its line and `scanned`; anything else adds to `refused`.

## What is the pack state?
- `pg_pack_state($lines, $short)`: all units scanned is `PG_PACK_PACKED` (1) even when short was asked; missing units with short is `PG_PACK_SHORT` (2); otherwise `PG_PACK_OPEN` (0). An empty pack never closes. `missing` is product_id => units (`pg_pack_missing()`).

## What pins them?
- `tests/pick_pack_test.php` (PP1a) and `tests/pick_waves_test.php` (PP3: shipped out, the drawer rule, ticks, status, tone, single-item address).
