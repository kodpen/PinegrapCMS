---
name: workspace-record-quick-edit
description: Plan (2026-10-19) for editing a product's fields from the Workspace without an assistant: changes.php checks and writers reused, drawer form, /price and /stock; slices R1-R4, open questions
---

## What is this plan?
- Owner request (2026-10-19): the assistants can change a product from a channel (proposal → apply), a person cannot. Give the record drawer and the chip menu a direct "Edit fields" path with the same checks, rights and log lines, no AI involved.

## How are records written from the Workspace today?
- Only through `includes/workspace/changes.php`: a catalogue `ws_change_types()` (kind, limit, label, column per field), rights in `ws_change_allowed()` (product, stock, order, product_group, offer → the `ecommerce` right: role ≤ 2 or `user_manage_ecommerce`), typing and diff in `ws_changes_input()`, apply-time stale check in `ws_change_apply_record_check()`, writers `ws_change_write_<kind>()`.
- Three callers: assistant proposals (ai.php, api_claude.php), AI bulk (bulk.php), and the scheduled action of type "change" (`ws_scheduled_change()`, staff only, needs an action plus "Run now") — the only path without AI.
- The record drawer (`openRecord()` in workspace.js ~16928, data from `ws_record_refs`) shows channels, tasks and messages about the record, no field; the chip menu (`chipMenus()` ~1594) has no edit item.
- Panel twin to match: `pg_pb_quick_update()` in `product_builder.php` (bin refusal, price in minor units, stock flag and timestamp).

## What are the slices?
- R1 Backend — done (PR #524, `includes/workspace/quick_edit.php`, memory docs/memory/workspace/workspace-quick-edit.md): actions `ws_record_fields` and `ws_record_fields_save`, the stale check, the applied `ws_ai_changes` row, the log line and the optional decision message.
- R2 Drawer form — done (PR #526): the "Fields" section in `openRecord()`, the chip menu item, `saValueInput()` extended, the decision tick, the stale line.
- R3 Commands: `/price <#product> 149,90` and `/stock <#product> 12` (Turkish `/fiyat`, `/stok`) in commands.php, answered with a confirmation card that calls R1; no silent write from a chat line.
- R4 API and memory: no new endpoint by default — `/products/{id}`, `/products/{id}/inventory` and the changes routes already exist; write `docs/memory/workspace/workspace-record-changes.md` describing changes.php, the scheduled change and the quick edit (none exists today).

## What was decided (owner, 2026-10-19, by default)?
- Short list: name/short description, price, published, stock, min/max quantity, brand, keywords; the other kinds (order, contact, erp_account, product_group, offer, calendar_event) get the generic form from the same catalogue. A quick edit is kept as an applied `ws_ai_changes` row (request_id 0). The decision tick is on when the drawer opened from a channel. No new API route.

## Where to start?
- R3 next (`/price`, `/stock` with a confirmation card calling `ws_quick_edit_save()`), then R4 (memory only). Verify on dev: a role-3 user with only `user_manage_ecommerce`, a binned product (refused), a concurrent panel edit (stale), dark theme.
