---
name: api-docs-webhook-events
description: The Webhook events section of the API reference (api_docs.php and integration.php/docs): the catalogue table linking to one block per event with the fields of data and the sample's delivered body
---

## What does the events section show?
- On both pages, one renderer (`api_console_events_html()`, `includes/api/console_view.php`): the intro block `#ep_events` (the envelope, the signature, the catalogue table), then one block per event, `#ev_<name>` with dots as underscores (`api_console_event_anchor()`).
- Each catalogue line links to its event's block. An event's block: its description, a "Data" table (field, type, meaning; a nullable field's meaning starts with "can be null") and an "Example": the body a delivery of its sample carries (`api_webhook_body()` with a fixed `created_at`), one key to a line (`api_console_event_example()`): written in one line, a sample with parcels wrapped where the screen ended and could not be read against the table.
- An event without fields says "This event carries no fields in data." and prints no table; a module's event names its module (ERP, Workspace) where an endpoint shows its scope.
- Since AU10 (#PR).

## Where do the blocks come from?
- From the rows of `GET /webhooks/events` (`api_webhook_event_rows()` over `api_webhook_events()`, `api_webhook_event_modules()`, `api_webhook_event_payloads()`), so the pages and an application's event chooser read one source. A module that is off adds no event and so no block.
- `api_console_event_blocks_html($rows)` is pure; the events' fields and samples are written in `webhook_payloads.php` (api-webhook-event-payloads.md), never in the renderer.

## What do the tests hold?
- `tests/api_console_events_test.php`: the anchor, a block's fields, types, nullable note and envelope, an event with no fields, and on the live catalogue one block per event, each linked and each with an example.
- `tests/api_console_conventions_test.php` counts one `doc-ep-head` per endpoint, the intro, the errors block and one per event.

## What is left to other plans?
- The pages' opening and headings: plan `api-clean-modern` (AM12 also edits `console_view.php`, so it is not open beside a step here).

Related: docs/memory/api/api-webhook-event-payloads.md, docs/memory/api/api-docs-conventions.md
