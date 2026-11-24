---
name: pick-pack-waves
description: Orders › Pick waves (view_pick_waves.php, pick_wave.php, print_pick_list.php): access, waiting addresses, Make a wave, ticks on a phone, close, delete, leavers, the order code
module: pick_waves
---

## Who opens the screens?
- `pg_pick_validate_access()`: the store on, and role < 3, the orders right or the "Packing only" right (`user.manage_packing`); refused before step 11.0.264. Menu: "Pick waves" in Orders; a packing-only user gets its own row (id 29).
- Nothing on them reads a price, a total or an e-mail. The order number links to the order screen only for those who see orders (`pg_pick_user_sees_orders()`).

## Which addresses wait to be picked?
- `pg_pick_waiting_addresses()`: an address (`ship_tos`) of an order placed in the last 90 days (`PG_PICK_WAITING_DAYS`; `order_date` is indexed), `complete` or `exported`, `paid_at` > 0, with an active line. The rule (`pick-pack-rules.md`) then leaves out shipped (a tracking number or a shipped unit), packed and in an open wave.
- Carrier: the method's `carrier`; marketplace: `marketplace_order_map.account_id`; location: `orders.stock_location_id`, 0 as the default; ship-by: `ship_tos.ship_date`, else paid day plus `handle_days`.

## How is a wave made?
- "Make a wave" opens a drawer: Carrier, Marketplace, Stock location (each drawn only when there is a choice), Ship-by day (Due today or late, Due by tomorrow), "Single-item addresses only". The foot's live line "N addresses, M units match" is read from `view_pick_waves.php?preview=1`; "Make the wave" waits while nothing matches.
- `pg_pick_wave_make()` under one `GET_LOCK` (`pg_pick_waves_lock_name()`), so an address joins one open wave at most: oldest ship-by first, at most 200; `rule` keeps the drawer's choice as JSON, NULL for a wave made by hand.
- Made by hand (the orders list's "Add to a wave") is not built yet: `pg_pick_wave_make($rule, $user_id, $ship_to_ids)` takes the chosen addresses.

## What does a wave's screen show?
- Head: the name ("Wave 12" while `name` is '') and its pill: Open (blue), All picked (teal), Closed (grey). "Print the pick list" fill, "Close the wave" quiet. Segments "Picking list" and "Addresses" with their counts.
- Picking list (`pg_pick_wave_lines()`): one row per product and location, shelf address in the figure type, picture, name, code and variant options ("Size: M"), units, "for N addresses". Shelf at the order's location, else location 0. The whole row is the tick's label; a tick posts `ajax=1` and greys the row (`.is-read`), which moves to the end; the bar reads "3 of 8 picked".
- Ticks: `pick_waves.picked` (line keys), per-wave lock; all ticked is All picked, an untick Open.
- Addresses: order, ship-by day (amber pill today, rose when late), units, pack pill (Waiting, Packed, Short); an order cancelled since reads "Cancelled, taken out" in a grey row.
- "Picking list" and "All picked" in English: "Pick list" and "Picked" were other keys already ("Açılır liste", "Seçildi").

## When does an address leave a wave?
- `pg_pick_waves_sync_leavers()` runs when the list or a wave's addresses are read: a cancelled order gets `removed_at`, leaves the pick list and the counts.
- Close the wave: status Closed, `closed_at`; its addresses not packed wait again. Delete (row menu, with a confirm): only while no address has a packed or short pack; its `pick_wave_orders` rows go, an open pack's `wave_id` becomes 0.

## What is printed?
- `print_pick_list.php`: light paper, the rows with a box to tick, then the wave's orders as order codes. The packing slip (`print_packing_slip.php`) prints the order code under the order date.
- Order code: Code 128 of `orders.order_number`, drawn by `assets/lib/JsBarcode` before the print dialog; the Pack screen (PP4) scans it.

## What was not checked?
- The functions ran on a MariaDB with sample orders (make, ticks, a leaver, close, delete); the screens were not opened in a browser.

Related: docs/memory/commerce/pick-pack-rules.md, docs/memory/commerce/pick-pack-tables.md, docs/memory/commerce/pick-pack.md
