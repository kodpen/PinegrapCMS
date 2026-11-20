---
name: delivery-tower
description: Plan (owner 2026-11-20, poll 178): delivery times measured per carrier and province, problem parcels found, a problem desk, events, a carrier scorecard; DT1 to DT6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 178, message 10566): "Evet, plana girsin": measured times, finding problems, the problem desk, events, the carrier scorecard with its suggestions, API.

## What is there today?
- The hourly tracking job asks each tracking driver where an open parcel stands and writes it through the shipment write path (`commerce/shipment-tracking.md`, `commerce/shipment-write-path.md`); carrier webhooks write the same way.
- A parcel row has `carrier`, `status` (created, in_transit, out_for_delivery, delivered, returned, exception), `status_at`, `shipped_at`, `delivered_at` (`commerce/shipment-row-schema.md`); `ship_tos.delivery_date` keeps checkout's estimate.
- `returned` and `exception` are only a badge (`pg_shipment_status_label()`): nobody is told. Nothing notices a parcel long in transit or a label never scanned.
- Checkout's arrival comes from the method's `base_transit_days` and the country's `transit_adjustment_days`, typed by hand; nothing measures a carrier.
- Labels: one write path, bulk, automatic once paid (`commerce/shipment-label-auto.md`); quotes by `rate()` (`commerce/carrier-label-rate.md`).

## What binds it?
- Read after the fact, from the parcel rows; the tracking job, label creation and checkout are not entered.
- One status writer stays `pg_shipment_record()`; the plan writes only its own problem records.
- A marketplace order's buyer is never written to by the shop (`commerce/shipment-tracking-hunt.md`, #10661); its parcels are measured and listed.
- Alerts to staff through `pg_ops_alert()` and its channels, once a day per code; the desk is the record.
- The plan sends no customer message itself: flows' boxes do.
- A carrier suggestion never changes a method that names a carrier, nor the automatic label's rule (`workflow/decisions-made-product-39.md`).
- Names and addresses only to readers with the orders right; a parcel's address is never in an event beyond the province.

## What does the architect decide?
- Schema: a `shipment_problems` table (parcel, kind, found, assignee, note, closed, closed by, how), several problems per parcel over time; or columns on `shipping_tracking_numbers`?
- Measured times in one `config_text` summary rebuilt nightly, as the Customers Report?
- "Not handed over": 1 working day after the label with status still `created`, counted on the method's ship days; only parcels whose status is read (tracking driver, webhook, marketplace)?
- The customer's notice: only the `shipment.problem` event for flows, or also a built-in e-mail as `order_shipped`'s?
- The arrival suggestion per method only (no per-province transit field); a per-province figure later?

## What is built?
- Features: `delivery-tower-features.md` (T1 to T14). Steps: `delivery-tower-steps.md`. Claim as `[plan:delivery-tower] <step> — <topic>`.

## What is left out?
- Opening a claim with the carrier by its API, reshipping by itself, a delivery promise per province at checkout, carrier price tables: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the desk and the scorecard are seen on dev after DT3 and DT4.
