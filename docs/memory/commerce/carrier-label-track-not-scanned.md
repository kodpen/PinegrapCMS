---
name: carrier-label-track-not-scanned
description: Label drivers' track() for a shipment the carrier has not scanned yet: success with status created via pg_label_track_not_scanned(), never a failure that stops the tracking job's driver (#10660)
module: carrier_labels
---

## What does track() answer for a label not scanned yet?
- A shipment the service knows but its carrier has not scanned (no status, or the service's `UNKNOWN`) is a success with status `created` and no time, built by `pg_label_track_not_scanned()`; the row already holds `created`, so the tracking job writes nothing and its failure count resets (#10660).
- Why: a failure counted toward the job's three-in-a-row stop (`PG_SHIPMENT_TRACKING_FAILURES`), so three fresh labels side by side stopped the driver for the run, deliveries were found hours late and the daily log called the text a service failure.
- Per driver: Geliver and Shippo no tracking code or `UNKNOWN`; Kargonomi and Basit Kargo an empty status; Sendcloud no carrier number, no event, or phase and exception empty/none/unknown; Aras no row with a waybill.
- Still failures: a status code the driver does not know (a new one is never read as `created`), a refusal, an unreachable service, and a webhook message without a status, so a message cannot move a parcel on its way back to `created`.

Related: docs/memory/commerce/carrier-label-drivers.md, docs/memory/commerce/shipment-tracking.md
