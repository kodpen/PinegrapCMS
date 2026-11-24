---
name: api-webhook-event-scopes
description: Webhook events follow the read scope of their data: the event → scope map, the 403 on create/change/sample, the skip at enqueue, the Events tab line and the one bell row per subscription
---

## Which scope may receive an event?
- The scope that reads its data through the API: `api_webhook_event_scopes()` (`includes/api/outbound/webhooks.php`), beside the catalogue. Core: `api_webhook_core_event_scopes()`; modules through `api_module_contributions('webhook_event_scopes')`: `erp_webhook_event_scopes()`, `ws_webhook_event_scopes()`, and the flows/mail-alone picks of the latter.
- Examples: `order.*`, `shipment.*`, `cart_quote.*`, store zones/tax zones/shipping methods/currencies, `ticket.checked_in` → `orders:read`; `customer.*`, `subscription.*`, `mail.*`, `whatsapp.received`, feedback, volunteer, course → `customers:read`; `credit.*`, `referral.rewarded` → `gift_cards:read`; `erp.receipt.*` → `erp_cash:read`, other `erp.*` → `erp:read`; `workspace.task.*` → `tasks:read`, `workspace.app.command` → `workspace:commands`, other `workspace.*` → `workspace:read`.
- A write gives its read (`api_webhook_scope_held()`, pure; written there too because queueing runs on every screen without `scopes.php`).
- Why: #11442 (PR #11446). An application with only `webhooks:manage` was sent order and customer payloads that `GET /orders` refused it. Architect (2026-11-24): filter at create/change and at enqueue, old subscriptions too, nobody cut off silently.

## What must a new event bring?
- Its line in the map of its catalogue (core or module). `tests/webhook_event_scopes_test.php` refuses a catalogue event without one, a map entry outside the catalogue, and a scope that is not in a scope group. An event with no site data (`webhook.test`) is not in the catalogue and needs none.

## What is refused?
- `POST /webhooks` and the panel's New subscription (`api_webhook_create_check(..., $granted)`), `PATCH /webhooks/{id}` with `events`, `POST /webhooks/{id}/test` with `event` and Send a sample (`api_webhook_test_refusal(..., $granted)`).
- The answer: 403 `insufficient_scope`, field `events`, "The application lacks {scope}, which reads the data of {events}. Give it that permission first." (`api_webhook_scope_refusal()`). The API passes the effective scopes; the panel the stored ones.

## What happens to a subscription made before?
- `api_webhook_enqueue()` reads `api_apps.scopes` with each subscription; one whose application lacks the event's scope is skipped, never queued.
- The Events tab shows under it, per missing scope, "Not delivered: the application lacks {scope}. Give it that permission and save." with "Open Permissions", which opens the drawer's Permissions tab (`blocked` of the row, `api_webhook_hook_blocked()`).
- The first skip writes one bell row (`api_webhook_scope_skip_note()`): action `webhook_scope`, `form_id` the subscription, `order_id` the application, `title` the scope; seen by administrators (role 0); opens `api_settings.php`. Once per subscription while that row is kept (looked up by `action` and `form_id`), not once per event.
- The owner ceiling (`api_owner_scopes()`) is not asked at enqueue: only the application's own scopes.
