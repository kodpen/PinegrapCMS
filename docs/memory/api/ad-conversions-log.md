---
name: ad-conversions-log
description: Ad conversions SC6: the Events card on each service's Connections page (daily counts, last 50 events), the ad_events_refused operations alert, GET /reports/ad-conversions
module: ad_conversions_log
---

## Where is it?
- `includes/fn/ad_conversions_log.php`, loaded from `functions.php` after `ad_click_ids.php`; tests `tests/ad_conversions_log_test.php`. Read only: it sends and changes no row. The queue it reads: `ad-conversions-queue.md`.

## What does a service's page show?
- `connections.php?service=meta_capi|ga4_mp|tiktok_events`, under the Visitors and privacy gate: an "Events" card after "Connection details" (`pg_ad_conversion_log_card()`, appended to `$page['details']` in `connections.php`).
- "Daily counts": the last 7 days, newest first, each a connect-style row: the day, "n sent, n failed · n% with the person", a rose "All refused" pill on a day with failures and nothing sent.
- "Last events": the newest 50 rows as `pg-list-row`: event name, time (sent time once sent), "With the person" or "Without the person", the order linked to `view_order.php`, the reason for a refusal or a retry, a status pill.
- Pills: "Waiting for the order", "Waiting to be sent", "Tried again later" gray; "Sent" teal; "Refused" rose. No row yet: the empty state "No event yet.".

## What is never shown?
- The row's `ids` column is never selected; the log and the report carry no e-mail, phone, click id, browser id or address, only whether the event had the person.

## How is a day counted?
- A row counts on the site day of its `created_at` (offset of the site zone at reading time, so a day next to a clock change may take an hour of its neighbour). Sent is status 2, failed is status 3. The share is the sent events with the person over the sent events; null with none sent.

## When does the operations alert go?
- Code `ad_events_refused` (warning, `connections.php`), source `ad_conversions` in `pg_ops_alert_sources()`: once a day, for yesterday, each connected service with failures and nothing sent. The day is claimed in the alert state file (`ad_conversions` key); the alert's subject is the service, so each service is told once a day. The detail names the service, the count, the day and the last reason.

## What does the API answer?
- `GET /reports/ad-conversions`, route `reports.ad_conversions`, scope `visitors:read`, type `AdConversionsReport` (`api_ad_conversions_report_schema()`, presenter `api_ad_conversions_present()` in `includes/api/resources/reports.php`). An MCP tool by the route rule. No write route: keys are typed only on the panel.
- Per service: `service`, `name`, `connected`, `events`, `waiting` (status 1), `totals` and `days` (30 days: `sent`, `failed`, `sent_with_person`, `share`), `errors` (the 10 newest refusals: `at`, `event`, `status_code` or null, `error`).

Related: docs/memory/api/ad-conversions-queue.md, docs/memory/platform/ops-alerts.md
