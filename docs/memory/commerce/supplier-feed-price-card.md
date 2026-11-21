---
name: supplier-feed-price-card
description: Supplier feed screens (SF3b): price rule and rules by category or brand, test pull prices and Floor pill, Held prices, Pull now, Pull history, the product's "Price from the feed"
module: supplier_feed_screen
---

## What does the price rule in card 3 save? (F4)
- Under Prices, once the feed was read: Margin %, Fixed amount (the feed's currency), Rounding as segments (None, Whole, ,90, ,99), Minimum margin % (the floor), the formula line. Kept in `supplier_feeds.rules` by `pg_supplier_feed_rules_input()`: `margin`, `fixed` (minor units), `rounding`, `floor_margin`, `specific`.
- An empty margin saves no `margin`, so the sync writes no price and only stock follows (`supplier-feed-sync.md`). Percents 0 to 1000, "12,5" read.
- "Rules by category or brand": rows of kind (Category, Brand), name, margin, fixed amount; "Add a rule" copies a `<template>` row, the cross removes one. A row keeps only what it sets (`{"category": "Mutfak", "margin": 30}`); the rest comes from the feed's own. A row with neither margin nor fixed amount, no name, or the same kind and name twice is refused. The screen makes no row naming both; `pg_supplier_feed_rule_pick()` still reads one.

## What does the test pull show of prices?
- `pg_supplier_feed_test_diff()` with `$pricing` (the feed's rules, rate, VAT): each row has the buy price (net of VAT as the sync makes it) and the selling price; "Floor" (amber pill) where the floor lifted it (`pg_supplier_feed_price_parts()`). A buy price the sync would hold shows "Held: the price stays".

## How are held prices decided?
- Card "Held prices" first in the form column, only while an item has `held_price`: last and held buy price, Accept and Reject (`pg_supplier_feed_held_decide()`); "Accept all" at its head (`pg_supplier_feed_held_accept_all()`); 20 rows, the rest in the "Show all" drawer `#sf_held_all` (500 at most).
- Accept: `buy_price = held_price`; the selling price follows at the next pull, which reads that price in range. Reject: `held_price` 0; a pull with the same price holds it again.

## What does Pull now do?
- On the list row, in the status column and at the test pull's foot, for a feed with saved fields that is on or paused (`pg_supplier_feed_pull_button()`).
- `pg_supplier_feed_pull_now()`: up to 20 s of `pg_supplier_feed_pull(..., 1, $drop_ok)` in the request; the job goes on with the rest. A paused feed asks first (the pause reason in the confirm), is switched on and pulled with `$drop_ok`, past the mass-drop brake.

## Where is the pull history?
- "Pull history" (quiet) in the status column opens `#sf_runs`: the last 30 runs, date, state pill, By hand or Scheduled, the counts, the reason and the first 5 failed lines.

## What does the product screen show?
- `product_builder.php`'s side column, under Stock by location: card "Supplier feed" (`pg_supplier_feed_product_card()`) for a product a feed's item carries: each feed with its link, buy price and last seen, and the switch "Price from the feed" (F5). Saving the product writes `follow_price` on all its items, only when the switch moved (`supplier_feed_follow_was`).

## What keeps the form safe?
- The form starts with a hidden default submit (Save, or Read the feed before a read), so Return never presses Accept or Pull now (`ui/return-key-default-button.md`).

Tests: `tests/supplier_feed_screen_test.php`.
