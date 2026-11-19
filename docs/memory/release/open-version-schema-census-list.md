---
name: open-version-schema-census-list
description: Census of the schema 2026.11.0 adds (SC1): what no code reads or writes, what was found replaced or redundant, and the count of what is in use, for the architect's remove-or-keep marks
---

## What was counted?
- Every table, column and index the sub-steps of `includes/migrations/2026.11.0.php` add on `development` of 2026-11-19: 199 sub-steps, 175 of them adding schema. What a released version brought is left out (none of the names below is in an older migration file).
- 113 tables (1,084 columns, 213 keys inside them), 232 columns and 29 indexes added to tables that already existed.
- By module: Workspace 44 tables, 73 columns, 10 indexes; commerce 24 tables, 55 columns, 5 indexes; ERP 3 tables, 2 columns, 2 indexes; the rest 42 tables, 102 columns, 12 indexes.

## How was use decided?
- Each name was searched in `pinegrap/` PHP and JavaScript with comments stripped, leaving out `includes/migrations/`, `get_tables()`, the `.min.js` bundles and `tests/`.
- A table is in use when a file has SQL on it (FROM, INTO, UPDATE, JOIN); all 113 have a reader and a writer.
- A column is in use when one function names both its table and the column, then checked by hand where the two were far apart: built names (`page_vitals_hourly` `<metric>_sum`, `order_returns.refund_*_notified_at`) count as in use.
- An index is counted with its columns; one that is the left part of another key on the same table is listed as redundant.
- Limit: a column whose name another table of the same function also has may still read as in use.

## What is unused?
- `whatsapp_inbox.read_user_id` and `whatsapp_inbox.read_at`, step 11.0.198 `upgrade_2026_11_0_whatsapp_inbox()`, PR #9351 (the rest). No code writes or reads them: the webhook inserts the row without them and the readers select other columns; the `read_at` hits are `whatsapp_outbox`'s. No index holds them.

## What is written but never read?
- `subscriptions.remote_id`, step 11.0.128 `upgrade_2026_11_0_subscriptions()`, PR #3236 (commerce): `pg_subscription_row_from_order_line()` in `includes/fn/subscriptions.php` writes `''` and nothing reads it. A driver of store mode may need it; no driver declares that mode yet.

## What is replaced or redundant?
- No column holding a value another column of this version holds, and no table whose rows a later step moved elsewhere, was found.
- `ws_saved_replies.idx_user (user_id)`, step 11.0.114 `upgrade_2026_11_0_workspace_saved_replies()`, PR #3090 (Workspace): the left part of `UNIQUE KEY uq_shortcut (user_id, scope, shortcut)` of the same table, which serves the same lookups.

## What happens next?
- The architect marks each item above removed or kept, with the reason for a kept one; SC2 takes out the removed ones per module (`plans/open-version-schema-census.md`; the rule: `released-vs-open-version.md`).
