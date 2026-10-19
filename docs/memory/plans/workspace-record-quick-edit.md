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
- R1 Backend: actions `ws_record_fields` (get) and `ws_record_fields_save` in actions.php. Get returns the viewer's editable fields with current values and kinds (`ws_change_record()`, `ws_change_current_value()`, descriptors as `ws_scheduled_js_config()` builds them). Save runs `ws_changes_input()` for one record, compares the client's `from` values like the apply-time check (answer "stale" on a difference), refuses a binned product, writes through the writers, logs "changed in the Workspace by <name>" and, when asked, posts a locked decision in the current channel (pattern changes.php ~2581). The writers' Claude-worded sentences get a source parameter (see the 2026-10 writer fix).
- R2 Drawer form: an "Edit fields" section in `openRecord()` with the most used fields first (name/short description, price, enabled, stock, min/max quantity, brand, keywords), "more fields" for the rest of the catalogue, inputs from `saValueInput()` (needs decimal, lines, keywords kinds and a short-text hint for `short_description`), a "post a decision in #channel" tick, save, stale message with a reload link.
- R3 Commands: `/price <#product> 149,90` and `/stock <#product> 12` (Turkish `/fiyat`, `/stok`) in commands.php, answered with a confirmation card that calls R1; no silent write from a chat line.
- R4 API and memory: no new endpoint by default — `/products/{id}`, `/products/{id}/inventory` and the changes routes already exist; write `docs/memory/workspace/workspace-record-changes.md` describing changes.php, the scheduled change and the quick edit (none exists today).

## What is still open?
1. Which fields go in the short list (default above) and whether order, contact and offer get the same form in R2 or later.
2. Whether a quick edit is recorded as an applied `ws_ai_changes` row (request_id 0, status applied) so the record's history in the drawer shows it (default: yes; no schema, `request_id` defaults to 0), or only in `log_activity`.
3. Whether the decision message is posted by default when the drawer opened from a channel (default: tick on).

## Where to start?
- Merge the writer fix PR first (bin guard, stock flag, source wording). Then R1 with a test for the pure descriptor builder in `tests/`, R2, R3, R4. Verify on dev: a role-3 user with only `user_manage_ecommerce`, a binned product (refused), a concurrent panel edit (stale), dark theme.
