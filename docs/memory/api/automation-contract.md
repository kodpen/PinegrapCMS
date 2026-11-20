---
name: automation-contract
description: pg_automation_contract(), the triggers, actions and searches an automation platform app is written from; its scopes, the test that holds it, what was left out
module: automation_contract
---

## What is it?
- `pg_automation_contract()` in `includes/fn/automation_contract.php`: a list of rows, data only, nothing reads it at request time. Not loaded by `functions.php`; a caller `require_once`s it.
- A row: `key` (stable name), `kind` (trigger, action, search), `module` ('' core, `erp`, `workspace`), `scope`.
- A trigger adds `events`, `record` and `record_ids` (each `{placeholder}` of the record route => the field of the event's `data`, a dot for nested: `table.id`), `list` and `list_ids`, `newest`.
- `newest`: the query parameters that list newest first (`sort=placed_desc` on `/orders`, `sort=newest` on the other core and ERP lists, `dir=desc` on a table's rows); `array()` when the list already does (`/returns`); `null` when it cannot yet (the Workspace task list). How `sort=newest` walks: `api-list-newest-first.md`.
- An action: `route` (a POST route). A search: `route` (a GET list) and `by` (its query parameters).
- `pg_automation_contract_scopes()`: `meta:read`, `webhooks:manage` and every row's scope, each once. The "Automation platform" starting point of New application ticks these.

## What does the test hold?
- `tests/automation_contract_test.php`: each event in its module's catalogue (core `api_webhook_events()`, `erp_webhook_events()`, `ws_webhook_events()`); each route in its module's route table under the row's scope and method; the ids fill every placeholder of the record and list paths; a `newest` parameter is one the list takes; a search's `by` is a query parameter of its route.
- No route the contract names is marked `moves_money`, and no scope it asks for holds such a route (`api-plugin-scopes.md`). So `orders:write` and `customers:write` (counter sale, subscription resume) stay out, and with them "Create customer" and "Update order".
- The keys are pinned: a key may be added, never dropped or renamed, since a published app relies on it.

## Which triggers are in?
- Orders: `order.created`, `order.status_changed`, `order.shipped`, `order.cancelled` (record `/orders/{id}`, list `/orders` newest first).
- `order.return.requested`: record `/returns/{id}` by `return_id`, list `/returns` (newest first already).
- `customer.created`, `form.submitted` (record by `form_id` and `id`), `product.created`, `stock.low` (by `product_id`, list `/products`), `subscription.renewed`/`.failed`/`.cancelled`.
- Workspace: `workspace.task.created`/`.completed`, `workspace.wtable.row.created` (only tables shared in a public channel, as the event is). ERP: `erp.invoice.created`/`.paid`, `erp.quote.created`/`.accepted`.
- `newest` is `sort=newest` on customers, form submissions, products, subscriptions and the ERP invoice and quote lists, `dir=desc` on a table's rows (PR #10174); `null` only on the Workspace task list, whose files were held by other PRs then.

## Which actions and searches?
- No labels: each app names its own triggers in its platform's words.
- Actions: create and update product, set stock, create form submission; with the Workspace: create task, send message, add workflow table row.
- Searches: order by `order_number`/`email`, customer by `email`/`search`, product by `sku`/`barcode`/`search`, form submission by `reference_code`, ERP invoice by `number`.
- ERP writes are left out: `erp:write` also issues and cancels invoices.

## What was left out, and why?
- `workspace.message.created`: no route reads one message by its id; `/workspace/channels/{id}/messages` lists a channel.
- `stock.low` has no list of low-stock products: its samples are `/products`. It fires only where `ECOMMERCE_LOW_STOCK_THRESHOLD` is set.

Related: docs/memory/api/api-plugin-scopes.md, docs/memory/api/api-webhook-events.md
