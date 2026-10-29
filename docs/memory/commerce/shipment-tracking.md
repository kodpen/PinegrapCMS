---
name: shipment-tracking
description: Carrier status polling (2026.11.0, PR #3147): shipment_tracking_job hourly in the light lane, which parcels are open, track() answers written back through the shipment write path, budgets, places
module: shipment_tracking
---

## What does the job do?
- `shipment_tracking_job.php`, entry `shipment_tracking_job` of `pg_cron_jobs()` (hourly, light lane). `pg_shipment_tracking_run()` (`includes/fn/shipment_tracking.php`, not in `functions.php`: required by the job and the Shipping card's save) asks the driver that made each open parcel where it stands (`track()` through `pg_label_call()`, `carrier-label-drivers.md`) and writes the answer back. The rule is in `plans/shipping-carrier-labels.md` ("What is decided?").
- Switched on with a service: `pg_label_settings_save()` calls `pg_shipment_tracking_job_switch_on()` when a driver that can track is saved on, as İYS and the feeds do. Without e-commerce or a ready tracking driver a run records itself and reads nothing.

## Which parcels are open?
- `pg_shipment_tracking_open()`: an `active` label with a reference, not a return label, of a driver that is on, ready and defines `track` (`pg_shipment_tracking_drivers()`), made in the last 60 days (`PG_SHIPMENT_TRACKING_DAYS`), joined to its parcel row on (`ship_to_id`, `number`) with `delivered_at` 0 and status neither delivered nor returned, on an order not incomplete or cancelled. A pruned parcel row drops its label out.
- Return labels are left out: their arrival is the goods coming back, never `order.delivered`.

## What is written back?
- Only through `shipment-write-path.md`: `pg_shipment_tracking_decide()` (pure) takes a status other than delivered, and the carrier and tracking page the row lacks; `pg_shipment_tracking_apply()` writes them by `pg_shipment_record()` with `existing_only` (a parcel removed meanwhile is not added back and gets no arrival), then for delivered calls `pg_shipment_delivered($ship_to_id, <the carrier's time>)`: `order.delivered`, the `order_delivered` e-mail and the SMS, once per address. Each arrival is logged ("Shipment Tracking: Order N (number) was delivered", existing texts).
- The parcel keeps the label's number: a carrier number given later (Geliver's `trackingNumber` beside the barcode) is not swapped in, which would cut the row from its label; its tracking page is written.
- A failed answer writes nothing; a driver's failures are logged once a day.

## What are the budgets?
- At most 100 parcels a run (`PG_SHIPMENT_TRACKING_BATCH`), no question after 90 s (`PG_SHIPMENT_TRACKING_SECONDS`; the script sets the time limit 120 s above). Drivers are asked in turn (`pg_shipment_tracking_queue()`); one that fails 3 times in a row (`PG_SHIPMENT_TRACKING_FAILURES`) is asked nothing more in that run and the others go on.
- Each driver keeps its place (the last label id asked) in `data/temp/shipment_tracking.json` with the time its failures were last logged, saved after every parcel, so a run the server stops still moves on. The next run reads after the place, so every open parcel takes its turn and a stopped driver's parcels wait after it; a run that reached the last open parcel starts the round again (`pg_shipment_tracking_next_place()`).

## How is it tested?
- `tests/shipment_tracking_test.php`: the entry and its pick, Geliver fixtures through `pg_label_call()` into the decision (delivered, statuses, unreachable, refused with the token masked), queue, budgets, a failing driver, places, state, write-path pins. Not run against a live service or a sandbox.
