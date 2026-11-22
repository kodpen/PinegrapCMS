---
name: api-delivery-tower
description: GET /reports/delivery (the carrier scorecard, DeliveryReport) and GET /shipments/problems (ShipmentProblem list), orders:read, MCP tools (DT5, #10969)
---

## What does GET /reports/delivery answer?
- `api_reports_delivery()` in `includes/api/resources/reports.php`, route `reports.delivery`, scope `orders:read`, type `DeliveryReport` (`api_delivery_report_schema()`); the presenter `api_delivery_report_present()` is pure.
- `month` (YYYY-MM, else 422 `validation_failed`; default the current month, a future one reads as the current) and `period` `month` (default) or `year` (the twelve months ending with `month`).
- Read as the Carrier Scorecard screen reads (`pg_delivery_scorecard_read()`, `delivery-tower-scorecard.md`). Answer: `shipping`, `period`, `from_month`, `to_month`, `min_parcels` (20), `total`, `carriers` (`carrier` key or null, `name`, `figures`, `provinces` in a month view, `months` in a year view), `arrival` (`method_id`, `name`, `measured`, `set`), `measured_at` (the nightly summary).
- Figures: `parcels`, `enough`, `on_time`, `median`, `problem`, `returned` (shares in percent, one decimal). Under 20 parcels `enough` is false and every figure but `parcels` is null ("Too few parcels").

## What does GET /shipments/problems answer?
- `api_shipments_problems()` in `includes/api/resources/shipments.php` (new resource file, loaded by `includes/api/bootstrap.php`), route `shipments.problems`, `orders:read`, a list of `ShipmentProblem` (`api_shipment_problem_present()`, paired in `tools/check_api_schema.php`).
- Filters `status` open (default), closed, all; `kind`; `carrier`; `order_id`; newest found first, cursor on the id. The WHERE is the desk's `pg_delivery_problem_where()`, the rows its `_pg_delivery_problems_select()`.
- Fields follow the `shipment.problem` event (`commerce/delivery-tower-alerts-events.md`): order, parcel, carrier, status, `province` (state, else city), `days_in_transit`, `usual_days`, plus `open`, `found_at`, `closed_at`, `close_reason`, `assigned_to`, `assignee` (user name), `note`, `estimated_date`. Never the street address or the buyer's name.
- `close_reason`: `sent_again`, `refunded`, `found`, `other` by staff; settled by a status: `delivered`, `parcel_removed`, `order_cancelled`, `scanned` (not handed over), `status_changed` (`api_shipment_problem_close_reason()`, the desk's close label in words).
- Read only: assigning, the note, closing and the settings stay on the screen. Before the problem table exists the list is empty.

## How is it tested?
- `tests/api_delivery_tower_test.php`; `php tools/check_api_schema.php`. The OpenAPI tag `Shipments` has its sentence in `api_openapi_tag_sentences()`. Both MCP descriptions fit the 600-character budget; the rest is in `details`.

Related: docs/memory/commerce/delivery-tower-scorecard.md, docs/memory/commerce/delivery-problems-desk.md
