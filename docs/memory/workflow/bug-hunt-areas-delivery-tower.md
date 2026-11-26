---
name: bug-hunt-areas-delivery-tower
description: The delivery tower bug hunt (2026-11-26, #11800): rules, passes, desk, scorecard, chooser, API; two faults fixed, the hand-closed rule built, read clean, left
module: delivery_tower_passes
---

## Which area was hunted?
- Architect (2026-11-26): the delivery tower DT1a to DT5b (`commerce/delivery-tower.md`), never read by a hunt. Out of scope: alerts once a day and bell rows (`bug-hunt-areas-ops-alerts-once.md`), shipment tracking (#10661), shipping labels (#10795).
- Read: `includes/fn/delivery_tower.php`, `delivery_tower_passes.php`, `delivery_problems.php`, `delivery_scorecard.php`, `shipment_label_chooser.php`, `view_delivery_problems.php`, `view_carrier_scorecard.php`, `includes/api/resources/shipments.php`, `api_reports_delivery()`, the two routes in `includes/api/schema.php`, `pg_panel_shipment_label_choose()`, `pg_shipment_label_chosen_driver()`.

## What did the hunt's PR fix?
- Not handed over opened on every label made through a driver that does not track, after one working day: the label path writes its parcel with source `carrier` (`pg_label_shipment_options()`), which `pg_delivery_tower_status_is_read()` reads as a status that is read. Now `pg_delivery_tower_parcel_changes()` ignores source `carrier` on a labelled parcel; only a tracking driver (every webhook driver tracks) or source `marketplace` makes it read.
- The Create Label chooser suggested a carrier with one parcel on time ("On time 100% across the country") over one measured on hundreds: `pg_delivery_tower_summary_finish()` kept the country's on-time counts from the first judged parcel, while a province's needed its figure. Now the country's needs the country figure (20 parcels).

## What was read clean?
- Days by local date in the site's time zone (init sets PHP's default from `TIMEZONE`; the passes and the scorecard read it): across midnight, a Friday-to-Monday parcel, Friday label flagged on Tuesday, DST nights.
- Province key: "İstanbul" / "ISTANBUL" / "istanbul", a non-breaking space, state else city. Exactly 20 parcels gives a figure and "enough"; 19 does not.
- Lock `GET_LOCK(..., 0)`: a second run returns null; the open rows' unique key and `INSERT IGNORE` keep one open row per kind; a desk save has `closed_at = 0` in its WHERE.
- Budget: both walks keep their place (state file, `delivery_tower_pass.after`), at least one page a tick; the pass waits while a summary is half built. The end-of-walk close takes rows whose parcel or order went, or whose order is cancelled.
- Desk: gate and Settings (role < 3) checked on the server, token on every post, assignee only from the orders-right list, `back` reduced to the filters. Any problem id is open to the orders right, as every order is.
- Excel: text goes as inline strings, so a cell starting with `=` stays text. API: both routes `orders:read` (the orders right, `api_owner_scopes()`); only the province, never street or buyer name.

## What did the architect decide on the way?
- A Stuck or Not handed over problem closed by hand while the status held was opened again by the next hourly pass, with a new event, every hour. Asked of the architect; answer (2026-11-26, request 3065): reopening is not intended, a hand-closed kind stays closed until the status changes. Built in the same PR (`commerce/delivery-tower-rules.md`, "How does a problem close?").

## What was left?
- A summary due is read before the lock: a run that waited while another finished the summary may build it once more (time only, no wrong figure).
- The desk reads "Scanned by the carrier" for any kind a later status closed; the API says `status_changed` for kinds other than not handed over. A new text was not added (language shards held by other PRs).
