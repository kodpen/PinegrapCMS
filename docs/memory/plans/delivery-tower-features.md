---
name: delivery-tower-features
description: Plan delivery-tower, features T1 to T14: measured times, the four problem kinds, the desk, alerts, events, the scorecard, arrival and carrier suggestions, settings, API
---

## How is a delivery time measured?
- (T1) Per carrier and destination province (the address's state, else its city), over the parcels delivered in the last 90 days: days from `shipped_at` to `delivered_at`, the median and the 90th percentile; working days and calendar days both.
- (T2) At least 20 parcels for a province's figure; fewer: the carrier's figure across the country; under 20 in all: no figure and no "stuck" for it.
- (T3) On time: delivered on or before `ship_tos.delivery_date` (checkout's estimate); a parcel with no estimate is measured, not judged.

## Which problems are found?
- (T4) Stuck: in transit (not delivered or returned) longer than its carrier and province's 90th percentile plus one day.
- (T5) Not handed over: an active label whose parcel still reads `created` one working day after the label.
- (T6) Delivery problem: status `exception`. Returned: status `returned`.
- (T7) Each problem is recorded once per parcel and kind; a later status that settles it (delivered, scanned) closes it by itself with the reason. A delivered parcel ends every open problem.

## What is the problem desk?
- (T8) Orders › "Delivery problems": parcel, order, carrier, kind, since when, assignee, note; filters by kind, carrier, open or closed; close with a reason (sent again, refunded, found, other). Orders right; the customer's name only as the order screen shows it.
- (T9) Staff told through `pg_ops_alert()`: new codes `parcel_stuck`, `parcel_not_handed_over`, `parcel_problem`, type `shipping`, one bell row a pass with the count and the first few orders, once a day per code.

## How is it told outside?
- (T10) `shipment.problem` and `shipment.problem_resolved` through `pg_announce()`: webhook catalogue, payload fixtures, API reference blocks, the flow start box with a kind filter. Payload: order id and number, parcel number, carrier, kind, days in transit, the carrier's usual days, province; no street address. The payload says whether the order came from a marketplace (`order_type`), so a flow leaves that buyer out.

## What is the scorecard?
- (T11) Per carrier and month, then per province: parcels, on-time share, median days, problem share, returned share; the last 12 months, Excel.
- (T12) Arrival suggestion: when a method's typed transit days differ from its carrier's measured median by a day or more, the method's screen and the scorecard say so ("Ankara: measured 2.6 days, set 1"); nothing changes by itself.
- (T13) Carrier suggestion: for an order whose method names no carrier, the Create Label dialog orders the ready drivers by the province's on-time share, then by `rate()` price where it answers; a suggestion only.

## Who can read it from outside?
- (T14) `GET /reports/delivery` (scorecard) and `GET /shipments/problems` (`orders:read`); MCP tools by the route rule. Settings in `config_text`: each problem kind on or off, the one day of T5, the plus one day of T4.
