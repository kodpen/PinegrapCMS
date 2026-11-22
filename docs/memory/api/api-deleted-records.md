---
name: api-deleted-records
description: GET /changes/deleted (M8c): deleted record ids from the audit trail's delete lines, the covered types and why the others are not, the since rule (410 since_too_old), who is answered for which type
---

## What does GET /changes/deleted answer?
- `{type, id, deleted_at}` rows from `audit_log` lines with action `delete`, oldest first (`created_at`, then `id`), cursor `(created_at, id)`, `limit` 1-250. `since` is required; `types` is a comma list of API resource names, every covered type when left out. Handler: `includes/api/resources/changes.php`.
- Nothing else of the record. The answer also carries `types`: the types this caller was answered for.
- A record in the Recycle Bin is not deleted (its line is `recycle`) and is not listed; a binned product leaves `GET /products` all the same.

## Which types are covered, and why not the others?
- A type is covered only when every path that deletes its row writes a delete line (`api_changes_deleted_types()`). Covered: `products` (only `pg_pb_delete_product()` deletes a product row) and `offers` (only `_pg_offer_delete()`).
- Not covered, named in `GET /meta` `deleted_records.not_covered`: `orders` (`pg_local_order_discard()` deletes an incomplete counter sale with no line), `customers` (erasure deletes contacts under one `erase` line of the person), `pages` (a design template install), `files` (many attachment, upload and theme paths), `users` (an import replace).
- Asking for a not-covered or unknown type is 422 on `types`. A type becomes covered by hooking its last unhooked path (`core/audit-trail-writers.md`), then flipping `covered`.

## Who gets which type?
- The route has no scope of its own (`'scope' => ''`, `'mcp' => true`). A type is answered only when the caller's effective scopes hold its read scope; for the user role (3) also only where the type has no row rule narrower than the scope (`row_rule`). A type left out is not an error: `types` in the answer shows it.

## What is the since rule?
- The floor is the later of the retention cutoff (`pg_privacy_retention_cutoff(pg_audit_retention_years())`, 0 when every line is kept) and the oldest line's `created_at`; an empty trail answers from now. A `since` before it is 410 `since_too_old` (in `api_error_catalogue()`): the client reads the lists whole and asks again from that read.

## Tests?
- `tests/api_changes_deleted_test.php`: the type map, parsing `types`, the scope and role filter, the floor and 410, the row shape, the route row.

Related: docs/memory/plans/mobile-api-shelved-m8.md, docs/memory/core/audit-trail-writers.md, docs/memory/core/privacy-log-retention.md
