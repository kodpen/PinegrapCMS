---
name: workspace-record-changes
description: How the Workspace writes site records: changes.php pipeline and callers, Recycle Bin guard, stock flag, money ceilings, whose name the sentences carry, writes that die half way
---

## Where are records written from the Workspace?
- Only in `includes/workspace/changes.php`: catalogue `ws_change_types()` (per field: kind, limit, label, column), rights `ws_change_allowed()` (product, stock, order, product_group, offer need the `ecommerce` right), typing and diff `ws_changes_input()`, per-kind rules `ws_change_check()`, apply-time stale check `ws_change_apply_record_check()` (every `from` must still hold), writers `ws_change_write_<kind>()`, rows in `ws_ai_changes` (pending → applying → applied / dismissed / stale / failed).
- Callers: assistant proposals (ai.php, api_claude.php; applied by the person who asked through `ws_ai_change_apply` or `POST /workspace/changes/{id}/apply`), AI bulk (bulk.php), and the scheduled action of type "change" (`ws_scheduled_change()`: staff only, writers called directly, own log line). A person's own edit from the record drawer runs the same checks and writers: docs/memory/workspace/workspace-quick-edit.md.
- Invoices, waybills, receipts and e-documents are tag-only; a product's images, barcode, attributes and zones are not reachable.

## What does ws_change_check() refuse for the catalogue?
- A product, its stock or a group in the Recycle Bin (`recycled = 1`) for update, add and remove — at proposal, apply and bulk time — with the product screens' sentence. Before PR #516 a proposal or a scheduled change with `enabled = Yes` could switch a binned product back on, because `ws_change_record()` reads the row whatever its bin state. Delete stays with `ws_change_delete_blocked()`. Test: `tests/catalog_bin_test.php`.

## How do the writers keep the out-of-stock flag?
- `ws_change_write_stock()` stamps `out_of_stock_timestamp` ("out of stock since") only when the product goes out of stock, as `pg_pb_apply_stock_flag()` does; `ws_change_write_product()` recomputes `out_of_stock` when `track_stock` (column `inventory`) is switched. Before #516 the date was never stamped and switching tracking left a stale flag. Test: `tests/workspace_changes_test.php`.

## How are amounts bounded?
- A `money` field's limit is the column's ceiling in minor units: `price` and `extra_shipping_cost` carry 2147483647 (signed INT) and `ws_change_value()` refuses more, since the non-strict session would clamp in silence while the decision shows the asked amount; `credit_limit` (BIGINT) carries 0, no ceiling. Claude's `change_types` route shows it as `limit.max` (#516).

## Which sentences does a writer leave, and in whose name?
- Log lines go through `ws_change_log()`: silent under `$GLOBALS['ws_change_log_quiet']` (a scheduled action logs its own line), otherwise worded "on Claude's proposal" and switched to Pinegrap AI by `ws_ai_voice()`.
- Two sentences stay on the record itself: an order's default cancellation reason (kept on the order, sent with the refund, read by the customer in the cancellation e-mail) and the note on a deleted offer. `ws_change_sentence($by_assistant, $by_scheduled, $var, $by_person)` names the scheduled action or the person when `ws_change_source()` says one is writing (set and cleared around the writer call in `ws_scheduled_change()` and `ws_quick_edit_save()`), the assistant otherwise. Before #516 a scheduled cancellation told the customer "on Claude's proposal".

## What happens to a write that dies after Apply took the row?
- The lock stamps `decided_by`/`decided_at` (an MCP hand-back to `pending` clears them). `ws_change_sweep_stuck()`, run beside `ws_bulk_continue()`, fails a non-bulk row still `applying` 900 s later (`WS_CHANGE_APPLYING_TIMEOUT`; unstamped rows by `created_at`) and says part may be written: failed, not pending, so a half write is not repeated (owner's choice, issue #512). Test: `tests/workspace_change_stuck_test.php`.
