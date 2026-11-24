---
name: bug-hunt-areas-migration-steps-11
description: Bug hunt of the 2026.11.0 migration steps (area 13, #11376): no fault; run twice, cut off at 57 points, paused, upgraded from a real 2026.4.10 install, tables of schema-46 to -49 matched
---

## Which area was read?
- Area 13: the steps of `includes/migrations/2026.11.0.php` (11.0.1 to 11.0.276, called by `upgrade_to_2026_11_0()`), run on MariaDB 10.11 as `release/upgrade-test-from-tag.md` says.

## What did it find? (#11376)
- No fault. Nothing was changed in product code.

## What was run and found clean?
- A real 2026.4.10 install (302 tables) upgraded with today's files: the schema (columns, types, NULL, defaults, collations, keys, engines) equals a fresh 2026.11.0 install's, 462 tables.
- Run twice: `config.version` set back to 2026.4.10 and run again on the fresh install and on the upgraded site: no schema change, no row changed.
- Cut off half-way: the run stopped by an exception before write statement N (57 points: every data statement, the one after it, every 15th ALTER), then run again: same schema and same row count in every table as one clean run.
- Paused: the pass budget set to 0, so every `install_pause()` point pauses: the version ends, schema equal.
- Every table of the 2026.4.10 schema given two rows of defaults before the upgrade: the two UNIQUE keys added to old tables (`shipping_tracking_numbers.uk_parcel` after its de-duplication, `erp_stock_moves.uniq_line_product` widening `uniq_line`) are added; run twice too.
- `upgrade_2026_11_0_erp_invoice_recurring_day()` with seeded recurrences: only rows started on a cut 28th move, within their month.
- Indexes added to old tables stay under 767 bytes (a COMPACT row format from an old server still takes them). No `TEXT ... DEFAULT` other than `DEFAULT NULL`, no MariaDB-only syntax.
- Every table a step creates is in `get_tables()`; no step alters a table that a later step of the call list creates; no step function is left out of the call list.
- Tables of `decisions-made-schema-46` to `-49`: `waiting_places` / `waiting_desks` / `waiting_tickets` (no index on `status`; `token_hash` unindexed, read with the id), the five volunteer tables, `launch_summary_lines`, `ws_notes.owner_channel_id`, `ws_share_links`, the five `prototype_links` columns and `prototype_verdicts`: columns, types and keys as agreed; `erp_order_costs.source` ends with `supplier` in the CREATE and in the widening step.
- Defaults existing rows get were checked against each step's comment: those turning something on for running sites are documented in the step (operations alerts, update backup, speculation prefetch, `form_fields.translate_values`, record schema type `BlogPosting`).

## What was left as it is?
- A site missing a table that 2026.4.10 always creates (`ws_channels`, `ws_messages`, `ws_ai_requests`, `marketplace_product_map`, `marketplace_order_map`, `erp_accounts`) stops at that table's first unguarded ALTER, naming the statement; other steps guard the same kind of table. No released install lacks them (2026.4.4 creates them unconditionally), so this is no fault: only a table deleted by hand is missing, and the upgrade screen names it.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/release/upgrade-test-from-tag.md
