---
name: delivery-tower-rules
description: Delivery tower pure rules: province key, median and p90, working days, on time, the 20-parcel minimum, the four problem kinds, one open row per kind and its closing
module: delivery_tower
---

## What does the module hold?
- Only pure functions (DT1a, #10801; overview `delivery-tower.md`): no query. The nightly summary and the hourly problem pass (`delivery-tower-passes.md`) read the parcel rows and hand them in.
- Province key `pg_delivery_tower_province_key($state, $city)`: the state, else the city; trimmed, spaces collapsed, lower case, every Turkish i (İ, I, ı) folded to `i`, so "İstanbul" and "ISTANBUL" are one key. Neither: `''` (only the country figure applies).

## How are days counted?
- By local date in the site's time zone, not by hours: shipped Friday 16:00, delivered Monday 09:00 is 3 calendar days and 1 working day (`pg_delivery_tower_calendar_days()`, `pg_delivery_tower_working_days()`).
- Working days are Monday to Friday after the start date up to and including the end date. Public holidays are not known and count as working days.
- Median and 90th percentile: `pg_delivery_tower_percentile()` interpolates between ranks (the spreadsheet's PERCENTILE.INC), rounded to 2 decimals.

## When is a figure given?
- `pg_delivery_tower_figures($rows, 20, $tz)`: per carrier, a country figure and a figure per province with at least 20 parcels (`PG_DELIVERY_TOWER_MIN_PARCELS`). Each: count, median, p90, median_working, p90_working.
- `pg_delivery_tower_figure_for()`: the province's figure, else the carrier's country figure, else `null` (under 20 in all, or an unknown carrier): no "stuck" then.
- Left out: no carrier, `shipped_at` 0, delivered before shipped. Marketplace parcels are measured like any other.

## What is on time?
- `pg_delivery_tower_on_time()`: the delivery's local date on or before `ship_tos.delivery_date`. No estimate (`0000-00-00`, empty) or not delivered: `null`, measured but not judged.

## How is each problem found?
- `pg_delivery_tower_problems_found($parcel, $figures, $now, $settings, $tz)`.
- stuck: status `in_transit` or `out_for_delivery`, calendar days since shipping greater than its own carrier and province's p90 plus `stuck_extra_days` (default 1). Exactly p90 + 1 is not yet stuck.
- not_handed_over: status `created`, a label (`label_at`), the status is read (`pg_delivery_tower_status_is_read()`: source `carrier` or `marketplace`, or the carrier has a driver or webhook), and `not_handed_over_days` (default 1) working days fully passed since the label; the running day does not count. A Friday label is flagged on Tuesday.
- A labelled parcel's source `carrier` does not count (the label path writes it): only a tracking driver or a marketplace makes its status read.
- problem: status `exception`. returned: status `returned`. A marketplace parcel's problems are found and listed like any other; no message goes to its buyer.

## How does a problem close?
- `pg_delivery_tower_pass($status, $open, $found)`: a found kind already open is not opened again (one open row per parcel and kind).
- Architect (2026-11-26, agent channel request 3065): a kind closed by hand stays closed until the status changes, no days. Held: its latest hand close (`closed_by` > 0, any reason) is at or after `status_at`, or `status_at` is 0 (`pg_delivery_tower_held_kinds()`); all four kinds.
- `pg_delivery_tower_pass()` opens no held kind; closing stays. A late status dated before the close keeps it held (accepted). After a change the parcel is judged anew, one new row per change. No event, alert, text or setting for a held kind.
- A delivery closes every open kind with reason `delivered`. Otherwise a kind closes once the status left its own statuses: `scanned` for not_handed_over, `status_changed` for the rest. A stuck parcel still in transit stays open even when a new figure no longer finds it.

## Which settings are there?
- `pg_delivery_tower_settings_clean()`: the kinds on or off and the two day counts; its rules are in `delivery-problems-settings.md`.
