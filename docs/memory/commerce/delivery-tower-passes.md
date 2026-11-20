---
name: delivery-tower-passes
description: Delivery tower passes from the general job: the nightly measured-times summary (config_text, cursor in data/temp), the hourly problem pass, the lock, the time budget, the 100k-parcel timing
module: delivery_tower_passes
---

## What runs, and from where?
- `job.php` calls `pg_delivery_tower_job_run(pg_cron_time_left(PG_DELIVERY_TOWER_TICK_SECONDS))` (20 s) when commerce is on and the table is there (`pg_delivery_tower_ready()`). A tick with nothing due reads `delivery_tower_summary` (request cache) and `delivery_tower_pass`; when something is due both passes run under one `GET_LOCK('pg_delivery_tower.<database>')`, so overlapping ticks never walk together.
- A pass that opened rows logs "general job found {n} new delivery problem(s)". Staff alerts and events are DT2's.

## How is the nightly summary built? (T1 to T3)
- Due when there is none or it was built before the last local 02:00 (`pg_delivery_tower_summary_due()`, `PG_DELIVERY_TOWER_NIGHT_HOUR`).
- Walks parcels delivered in the last 90 days (`PG_DELIVERY_TOWER_DAYS`) by id, 1,000 a page, until the tick's deadline. Each parcel is packed into one integer (calendar days, working days, on-time mark) per carrier and province key, so 100k parcels stay a few MB; the walk keeps its place in `data/temp/delivery_tower_summary_<hash of database@host>.json` (taken only under a day old) and writes the summary once at the end.
- `config_text` `delivery_tower_summary`: `at`, `days`, `parcels`, `figures` (as `pg_delivery_tower_figures()` gives them, read by `pg_delivery_tower_figure_for()`), `on_time` (judged and on-time counts per carrier: `country` and the provinces that have a figure). Over 65,535 bytes, the provinces with the fewest parcels are left out until it fits (`pg_delivery_tower_summary_encode()`).

## How does the hourly problem pass work? (T4 to T7)
- Due an hour after the last walk started, or at once while one is under way (`pg_delivery_tower_pass_due()`; record `delivery_tower_pass` = `after`, `at`). It runs after the summary in the same tick only when the summary is done.
- Reads, by id, parcels on an order not incomplete or cancelled that are not delivered, have a number and were made, shipped or moved in the last 90 days, plus every parcel with an open problem whatever its age (`pg_delivery_tower_parcel_query()`). Older forgotten rows are not judged, so the first run does not flood the desk.
- Per page: the newest active non-return label per parcel (`label_at`, driver), the open kinds, and `pg_delivery_tower_parcel_changes()` (pure) calls the rules; a label whose driver is in `pg_shipment_tracking_drivers()` makes the status read. Opens are one `INSERT IGNORE` a page; a close sets `closed_at`, `close_reason` 1. `delivered_at` > 0 counts as delivered whatever the status column says.
- At the end of a walk, open rows whose parcel is gone or whose order is gone, incomplete or cancelled are closed with reason 1.
- Settings: `pg_delivery_tower_settings()` reads `config_text` `delivery_tower_settings`.

## How long does it take? (DT1b, #10847)
- Sandbox MariaDB, 100k parcels (70k delivered in 90 days, 20k in transit, 5k created, 3k exception, 2k returned), PHP 8.3 CLI: summary 1.4 s; first pass 1.1 s (30k parcels read, 24k rows opened); a repeat pass 1.3 s; peak memory 62.5 MB against 48.5 MB for `init.php` alone. A 2 s budget stopped after the summary and 21 pages and went on next tick.
- No index was added to `shipping_tracking_numbers` (no schema beyond the decided table); both walks go by primary key.
