---
name: n11-old-address-order
description: The 2026.11.0 data step 11.0.138 that swaps old n11-imported ship_tos rows (province in city, town in state) into the checkout's order (#3836)
module: shipping
---

## What does step 11.0.138 do?
- `upgrade_2026_11_0_n11_old_address_order()` in `includes/migrations/2026.11.0.php`, called after `upgrade_2026_11_0_tr_district_plate()` (11.0.134, which brings `ship_tos.district`). Decided in #3836 (b); data only, no schema change, no `install_heavy_tables` entry.
- Reads `ship_tos` rows of orders mapped to an n11 account (`marketplace_order_map` → `marketplace_accounts.provider = 'n11'`), 500 per query by id.
- For a row `pg_ship_to_reversed_tr_swap()` matches it swaps `city` and `state` and sets `district` to the old `state` when `district` is empty. The UPDATE repeats the values it read, so an edit made in between wins.
- One `install_note`, written only when rows were swapped. Skipped when `includes/fn/shipping.php` is out of date or the tables/column are missing.

## Which rows match?
- `pg_ship_to_reversed_tr_swap($row)` (`includes/fn/shipping.php`): country empty or read as `TR` by `pg_sales_map_canonical_country()` (`TR`, `Türkiye`, `Turkey`, any case/accents); `city` a province by `pg_sales_map_match_region('TR', …)` (81 names of `pg_sales_map_tr_regions()` plus the sales-map aliases such as `Afyon`); `state` no province.
- A row in the checkout's order (state is a province) never matches, so a rerun swaps nothing. A row with both a province in city and state is left alone.

## Why?
- n11 imports before #2004 stored n11's province in `city` and town in `state`; screens showed them reversed and `pg_label_recipient_district()` fell back to `city`, sending the province to the carrier as the district.
- Trendyol rows are not touched: its import was switched before release (#3861, #3835).

## How does it resume?
- It pauses (`install_pause()`) only after a swap once the pass budget is spent; the next pass reads from the first row again, and swapped rows no longer match, so every pause has made progress.

## Where is it tested?
- `tests/n11_old_address_order_test.php`: province, accent/case, country forms, the state guard, district only when empty, rerun finds nothing, the step's label and shape.
