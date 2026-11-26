---
name: customer-rhythm-events
description: Customer rhythm events (customer_rhythm_tell.php): stage changed, reorder due, the 7-day brake, when a product stops being named, comparison share, settings row, payload, flow stage filter
module: customer_rhythm_tell
---

## What is announced, and from where?
- The nightly pass (`customer-rhythm-store.md`) hands each written batch to `pg_customer_rhythm_tell()`, which calls `pg_announce()`, so webhooks and flows both hear it. The pass file itself never calls `pg_announce()` (tested). Since RY2.
- `customer.rhythm_stage_changed`: each move the pass makes (`moved`); a new row and a curve-only change are no move, so a first fill and a rebuild tell nothing.
- `customer.reorder_due`: the products of `pg_customer_rhythm_reorder_pick()`.
- Only for a row whose `contact_id` names a contact that is still there (one read per batch); a marketplace buyer has no row.

## When is a product named in a reorder event?
- At most one event per contact while the brake runs (7 days): since the key's `reminded_at` and the contact's latest under any key (`MAX(reminded_at)` by `contact_id`). Since #11767; before, a contact with orders under two addresses got two reminders a night.
- A product is named once per purchase: only when its window (due day minus the lead, 3 days) opened after `reminded_at`, and only until the brake's days after its due day, or a purchase years past due would be told every week.
- Brake and window end use the same days, so a window opening during a brake is still open when it ends.
- A product bought again since gets a new due day (`pg_customer_rhythm_due_products()`), so it can be named again.

## What does the comparison share do? (H12)
- With `share` on, `pg_customer_rhythm_held_back()` holds back one due customer in ten (crc32 of the key and the day): `reminded_at` is set with `held_back` 1 and nothing is announced. Off by default (architect, `customer-rhythm.md`).
- A told reminder writes `reminded_at`, `held_back` 0, `came_back_order_id` 0 before it is announced, so a run stopped between the two never tells twice.
- The summary (config_text `customer_rhythm`) keeps `reminders`: 'YYYY-MM' => [told, held back], 24 months, added from the pass state at its end. Who came back after a reminder (H13): `customer-rhythm-report.md`.

## Where are the settings?
- config_text `customer_rhythm_settings` JSON, read by `pg_customer_rhythm_settings()`: `on` (default on; off announces nothing and writes no reminder), `brake_days` (7, 1 to 90), `lead_days` (3, 0 to 30; the pass reads it at the walk's end), `share` (off). Form: Rhythm settings on the Customers Report (`customer-rhythm-report.md`).

## What does the payload carry?
- `pg_customer_rhythm_event_payload()`: `id` (contact), `stage`, `previous_stage` (null for none), `interval_days`, `days_since_last_order`, `chance` (per cent), `expected_value` (minor units, base currency), `last_order_id`, `last_order_number`. Stage names are those of `pg_customer_rhythm_stage_codes()` (`slipping` for Slipping away).
- Reorder adds `products` (id, name, short_description, quantity, due_at UTC), `products_text` ("2 × Coffee beans 1 kg") and `reorder_url`, the signed link that fills a cart (`customer-rhythm-reorder-link.md`).
- Fields, samples and catalogue lines in `webhook_payloads.php` / `webhooks.php`; the API reference blocks come from them. Area "Customers".

## How do flows start on them?
- `ws_events_catalog()` in the shop block once `pg_customer_rhythm_ready()`: record the contact, right `contacts`, filter `rhythm_stage` (`ws_events_rhythm_stages()`; `ws_events_filter_value()` reads `stage`). Outputs in `ws_flow_event_outputs()`; Customers area of quick setup.
- Both are on demand (`pg_event_workspace_on_demand()`): kept in `ws_events_in` only while a rule names them, as one pass may move thousands of customers.

## Tests?
- `tests/customer_rhythm_tell_test.php`; the pass test's hand-over line; `webhook_event_payloads_test.php` builders; the parity table's lines.
