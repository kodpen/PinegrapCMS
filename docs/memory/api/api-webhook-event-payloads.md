---
name: api-webhook-event-payloads
description: Fields and samples of webhook events (api_webhook_event_payloads()), GET /webhooks/events, POST /webhooks/{id}/test with event, the test that reads the announcing code
---

## Where are an event's fields and sample?
- `api_webhook_event_payloads()` in `includes/api/outbound/webhook_payloads.php`: event => `fields` (name => array(type, meaning)) and `sample` (data with every field, in the same order). A type ending in `?` may be null; types: integer, number, string, boolean, object, object[], string[], integer[].
- The catalogue's text stays in `api_webhook_events()`; the core's own list is `api_webhook_core_events()`, the modules' are merged after it.
- Every core event has them: the shop's events since #10149 (`api_webhook_event_payloads()`), the rest of the core since AU5 (`api_webhook_event_payloads_site()`: customers, users, contact groups, consents, pages, experiments, content review, zones, shipping methods, currencies, offers, files, forms, calendar reservations, campaigns, mail, WhatsApp). The modules' events come with AU6 (`plans/automation-platforms-steps.md`).

## What does the test hold?
- `tests/webhook_event_payloads_test.php` reads every `pg_announce()`, `api_webhook_enqueue()` and announcing helper (`pg_return_announce`, `pg_cart_quote_announce`, `pg_credit_announce`, `mp_order_announce`) with the tokenizer: a literal array's keys, a variable's last literal array, and payload functions run on a fixture. Per event, the keys of all places together must equal the fields.
- A field only some places write says so in its meaning (`source` of `inventory.changed`, the `location_*` fields, `recycled` of `product.deleted`).
- The test asks this of every event of `api_webhook_core_events()`. A choice of names written in place (`$new ? 'zone.created' : 'zone.updated'`) is read as those names. An announcement whose name is built elsewhere must be listed in `webhook_payloads_test_dynamic_calls()` with its family, else the test fails.
- So a key added to an announcement fails the test until its field and sample are added.

## What does GET /webhooks/events answer?
- `data`: one row per event of `api_webhook_events()`: `name`, `description`, `module` ('' core, `erp`, `workspace`; the flows and mail merged alone count as `workspace`), `fields` (name, type, nullable, description) and `sample`; `[]` and null for an event not written yet. Scope `webhooks:manage`. `GET /webhooks` keeps its list of names.
- The router reads `{id}` of `/webhooks/{id}` as digits only, so the path never reaches `webhooks.get` (tested).

## How is a sample sent?
- `POST /webhooks/{id}/test` with `event`: one of the subscription's own events with a sample, else 422 `validation_failed` on `event` (`api_webhook_sample_refusal()`). Queued for that subscription only under the event's name, never announced: no flow, channel or other subscriber hears it.
- The body is the real envelope plus `test: true` (`api_webhook_sample_body()`); `api_webhook_delivery_headers()` adds `X-Pinegrap-Test: 1` for such a body. No queue column marks it: the body does.
- Without `event` it is `webhook.test` as before, with no test header.

Related: docs/memory/api/api-webhook-events.md, docs/memory/api/automation-contract.md
