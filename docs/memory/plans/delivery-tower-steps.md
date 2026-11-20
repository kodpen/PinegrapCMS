---
name: delivery-tower-steps
description: Plan delivery-tower, steps DT1 to DT6: measured times and problem finding, alerts and events, the desk, the scorecard and suggestions, API, close-out
---

## Which steps are there?
- DT1 (M) first: measured times and finding problems.
  - The store the architect chose (a migration step in the open version if a table). New `includes/fn/delivery_tower.php`, pure: the province key, median and 90th percentile, working days, on time, the four problem kinds and their closing. The nightly summary and an hourly problem pass from the general job, bounded in time.
  - `tests/delivery_tower_test.php` red first: a province with too few parcels, a parcel without an estimate, stuck against its own carrier, a label never scanned over a weekend, a problem closed by a delivery, a marketplace parcel.
  - Timed on a copy with 100k parcels; the figure in the PR body. Features: T1 to T7.
- DT2 (S) after DT1: the `shipping` codes in `includes/fn/ops_alerts.php`, the two events through `pg_announce()`, the catalogue, fixtures, API reference, flow start box. Features: T9, T10.
- DT3 (M) after DT1: the Delivery problems screen under Orders, assign, note, close. Feature: T8.
- DT4 (M) after DT1: the scorecard screen and Excel, the arrival suggestion on the method's screen, the carrier order in the Create Label dialog. Features: T11, T12, T13.
- DT5 (S) after DT3 and DT4: `GET /reports/delivery` and `GET /shipments/problems`, their types, `php tools/check_api_schema.php`; the settings. Feature: T14.
- DT6 (S) close-out: every T number checked against the code with its PR; `commerce/delivery-tower.md` written, `commerce/shipment-tracking.md` pointing to it; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After DT1: DT2, DT3 and DT4. `includes/fn/ops_alerts.php` is also entered by `plans/sales-watch.md` SW1 and `includes/api/resources/reports.php` by SW5: check the open PRs before claiming.
