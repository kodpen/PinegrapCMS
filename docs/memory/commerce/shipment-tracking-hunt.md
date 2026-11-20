---
name: shipment-tracking-hunt
description: Bug hunt of the parcel status tracking (agent channel message 10333): files read, marketplace delivered notice fixed (#10661), fresh label "no status yet" stop left as #10660
module: shipment_tracking
---

## Which hunt read the tracking?
- Architect (2026-11-20, agent channel message 10333) named the area: `includes/fn/shipment_tracking.php` and `shipment_tracking_job.php`. Read by #10661.
- The start file of the tracking is `shipment-tracking.md`; this file holds only the hunt.

## What did it fix?
- A carrier's arrival on an order a marketplace sold sent the shop's `order_delivered` e-mail, SMS, WhatsApp message and push to the marketplace's relay address: `pg_shipment_tracking_apply()` called `pg_shipment_delivered()` with the notice on. The carrier webhook writes through the same function, so it did the same.
- Now `pg_shipment_tracking_notify()` keeps the notice back for `orders.type` `marketplace`, as `update_order.php` does; the open query reads the type, the webhook's row is read for it (#10661).

## What was left as an issue?
- #10660: every driver answers a shipment not scanned yet as a failure ("… gives no status for this shipment yet."), which counts toward the three-in-a-row stop meant for a service that is down; fresh labels next to each other stop their driver for the run and slow the round. The fix is in the label drivers (answer `created`), held during the hunt.

## What was read and found clean?
- Which parcels are open (`pg_shipment_tracking_open()`); one write per change in `pg_shipment_tracking_decide()` (carrier keys and tracking pages compare as stored); the arrival announced once per address, a poll and a webhook racing included; drivers asked in turn, the time and row budgets, the stop after three failures; where the next run starts (`pg_shipment_tracking_next_place()`) and the state file; the job's gate and summary.

Related: docs/memory/commerce/shipment-tracking.md, docs/memory/commerce/shipment-write-path.md, docs/memory/workflow/bug-hunt.md
