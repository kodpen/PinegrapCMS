---
name: delivery-tower
description: Delivery tower overview (carrier times, problem parcels, Delivery problems, Carrier Scorecard, label chooser): what binds it, its files, each feature's PR
---

## What is it?
- Owner (2026-11-20, agent channel poll 178, message 10566; `workflow/decisions-made-product-90.md`): each carrier's real delivery time per province, parcels in trouble found before the customer asks, a problem desk, events, a carrier scorecard with arrival and carrier suggestions, API.
- Built in 2026.11.0 by DT1a #10801, DT1b #10847, DT2 #10889, DT3 #10914, DT4 #10890, DT4b #10931, DT5 #10969, DT5b #11006; closed out 2026-11-22, every feature checked against the code.

## Where is each part?
- Pure rules (province key, median and p90, working days, on time, the four kinds, closing, settings): `delivery-tower-rules.md`.
- Table `shipment_problems`: `delivery-tower-problems-table.md`. Nightly summary and hourly problem pass, timing: `delivery-tower-passes.md`.
- Ops alerts, `shipment.problem` / `shipment.problem_resolved`, flow filter: `delivery-tower-alerts-events.md`.
- Orders › Delivery problems: `delivery-problems-desk.md`; its Settings drawer: `delivery-problems-settings.md`.
- Carrier Scorecard, arrival check, carrier order rule: `delivery-tower-scorecard.md`. Create Label chooser: `delivery-tower-label-chooser.md`.
- "Problems" / "Carrier scorecard" segments: `delivery-tower-segments.md`. `GET /reports/delivery`, `GET /shipments/problems`: `api/api-delivery-tower.md`.

## What binds it?
- Read after the fact from the parcel rows; the tracking job (`shipment-tracking.md`), checkout and label creation are not entered, but for the label path's one optional chosen driver (DT4b).
- `pg_shipment_record()` stays the one status writer; the tower writes only its own `shipment_problems` rows.
- A marketplace order's parcels are measured and listed, but its buyer is never written to (`shipment-tracking-hunt.md`, #10661); events carry `order_type` so a flow leaves them out.
- No built-in customer message: a flow on `shipment.problem` tells the customer. Staff are told by `pg_ops_alert()`, once a day per code; the desk is the record.
- A carrier suggestion never changes a method that names a carrier, nor the automatic label's rule (`workflow/decisions-made-product-39.md`).
- Names only to readers with the orders right; no event or API answer carries an address beyond the province.
- Architect (2026-11-20, message 10748): one table, `shipment_problems`, no column on `shipping_tracking_numbers` (owner yes, poll 192, `workflow/decisions-made-schema-40.md`); measured times in one `config_text` summary rebuilt nightly; "Not handed over" only for parcels whose status is read; one open row per kind; the arrival suggestion per method only.

## Which feature landed where?
- T1 to T7 measured times, on time, the four kinds, one open row, closing: rules #10801, passes #10847.
- T9 alerts, T10 events, catalogue, fixtures, flow start box: #10889. T8 desk: #10914.
- T11 scorecard and Excel, T12 arrival suggestion, the T13 order rule: #10890. T13 chooser on the order screen: #10931.
- T14 API routes, MCP tools and the settings: #10969. Segments between desk and scorecard: #11006.

## Where does the code differ from the plan's words?
- T9: the bell rows still link to the orders list (`view_orders.php`), not to Delivery problems.
- T10: a problem closed on the desk, or because its order or parcel went, is not announced; nor are rows opened by a site's first walk.
- T8: a problem's "What happened" shows label, shipped, the latest status, delivered and the problems; the parcel row keeps no full status history.
- T13: the chooser is a drawer from the right (a bottom sheet under 576 px) with a teal "Suggested" pill; a driver shipping with several carriers has no share.

## What is left out?
- A carrier claim by its API, reshipping by itself, a delivery promise per province at checkout, carrier price tables, the arrival suggestion per province: each a later yes.
