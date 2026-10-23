---
name: audit-trail-workspace
description: How Workspace writes reach audit_log through ws_change_write(): kind to table, recycle vs deletes the helpers write, offers, the job as actor for bulk and scheduled changes
---

## Where is a Workspace write recorded?
- `ws_change_write()` (`includes/workspace/changes.php`) wraps every `ws_change_write_<kind>()` call. Callers: `ws_change_apply()`, `ws_quick_edit_save()`, `ws_bulk_step()`, `ws_scheduled_change()`; a new one goes through it too (`tests/workspace_audit_test.php` fails on a writer called by name elsewhere).
- It reads the row before the write (not for a create), runs the writer, then `pg_audit_record()`; a create takes the id the writer answers. The writer's answer comes back unchanged; a line that cannot be written stops nothing; without `includes/fn/audit.php` nothing is recorded.

## Which kind becomes which line?
- `ws_change_audit_target($type, $action, $done)` (pure): product and stock → `products`, order → `orders`, user → `user`, page → `page`, file → `files`, offer → `offers`. Contacts, ERP accounts, product groups, forms, calendar events, channels, tasks and notes have no table in `pg_audit_tables()`: no line.
- `create` → `create`; a delete the writer answers `binned` (product, page, file into the Recycle Bin) → `recycle`; update, add, remove → `update`; a write that did not go through → none.
- A product deleted for good goes through `pg_pb_delete_product()`, an offer through `_pg_offer_delete()`: both write their own `delete`, so the wrapper writes none.
- A product into the bin goes through `pg_catalog_bin_product()`, which writes nothing itself: the `recycle` line is the wrapper's. If that helper ever writes its own line, drop the product `recycle` here or it doubles.
- Offers are compared as `_pg_offer_audit_state()` (conditions and actions included) and written with `pg_audit()`, labelled with the code, as the offer screen and the API do; `edit_offer_f.php` is loaded when missing.
- A product group switched on or off: `update_product_group_status()` writes one `update` per product it switches; the group itself is not traced.
- A drawer save that changes a product and its stock runs two writers: two lines.

## Whose name does a line carry?
- A proposal applied (`ws_ai_change_apply`) and a drawer save run in the person's own request: `panel` with that person; `POST /workspace/changes/{id}/apply` is `api` with the app.
- Bulk slices are carried on by `ws_bulk_continue()` on whichever operator's screen ticks or in the Workspace job, and scheduled actions run in the job. Both call the wrapper with `$as_job = true`: the writer and the line run inside `pg_audit_as_job(true)` (switched back in `finally`), source `job`, so an operator who only had a screen open is never named. Lines the writer's helpers write follow the same switch. Who applied a proposal stays on `ws_ai_changes.decided_by`.

## What stays out of the line?
- The Site Log sentence (`ws_change_log()`) and the decision message are separate. Only changed columns are kept; `user`, `user_user`, `*timestamp`, `seo_*` are left out by `pg_audit_tables()`.

Related: docs/memory/core/audit-trail-writers.md, docs/memory/workspace/workspace-record-changes.md, docs/memory/core/audit-trail-recycle-bin.md
