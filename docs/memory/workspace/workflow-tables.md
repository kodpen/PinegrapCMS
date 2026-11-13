---
name: workflow-tables
description: Workflow tables (11.0.110, #2746): the four ws_wtable* tables column by column, ws_wtable_access(), column changes; JSON person/link cells and the privacy reader
---

## What are the four tables, column by column?
- `ws_wtables`: `user_id` owner or maker; `name` (120 characters, PHP check); `description` (2000); `row_count` (the 5000-row limit, no `COUNT(*)`); `next_number` (`workflow-table-row-numbers.md`); `revision` bumped on every column change (screens reload columns; a row write is checked against the columns then); `created_at`, `updated_at`. `idx_owner (user_id, updated_at)` lists "my tables"; `channel_id` (11.0.191) names a channel's table (`workflow-table-channel.md`).
- `ws_wtable_columns`: `table_id`, `name`, `type` VARCHAR(16) (one of `ws_wtable_column_types()`), `options` JSON (decimals, currency, choices, expression…), `position`, `created_at`. `idx_table (table_id, position)`.
- `ws_wtable_rows`: `table_id`, `number` (stable, `idx_number`), `position`, `cells` MEDIUMTEXT JSON object by column id (money integer minor units), `revision` (409 `changed_meanwhile`), `created_by/at`, `updated_by/at`. `idx_table (table_id, position)`.
- `ws_wtable_shares`, as `ws_flow_shares`: `table_id`, `target_type` user|channel, `target_id`, `level` view|edit (channel: view only), `created_by/at`; `UNIQUE (table_id, target_type, target_id)`, `idx_target`.
- All InnoDB utf8mb4, no ENUM (a new type or share kind needs no MODIFY), no foreign keys: `ws_wtable_delete_final()` removes columns, rows, binned rows and shares.

## Why JSON cells and not a row per cell?
- A row write stays one `UPDATE … WHERE revision = ?`; a column delete needs no mass DELETE. Refused: a row per cell (#2746).
- No MySQL JSON functions: MariaDB 10.3 and MySQL 5.7 read JSON differently; filter and sort in PHP. The limits (50 columns, 5000 rows, 500 tables, `ws_wtable_limits()`) bound a table's read.

## Who reaches a table?
- `ws_wtable_access()` → owner | edit | view | '' (pure rule `ws_wtable_access_level()`, #2424): the owner; a person shared at view or edit; a channel share gives view to the channel's readers. No role gives access: an administrator reaches a personal table only through a share. A channel's table: the channel's rights.
- Owner gone (personal table; no user row, `ws_profiles.excluded`, no workspace right: `ws_rights_for_id()['member']` false) → nobody reaches it, shares included. A flow box on it pauses (`ws_wtable_flow_gate()`, `workflow-table-shares.md`).
- `ws_wtable_may()`: read view+, write edit+ (name, description, columns, rows), manage owner only (delete, shares, move).

## How do column changes behave?
- The column form changes name and options (`type_fixed`); the type changes in `workflow-tables-column-type.md`.
- A deleted column's cells stay in the rows' JSON until the row's next write (`ws_wtable_cells_merge()` drops columns the table lacks); reads skip them. Column ids are never reused.
- A table or row delete goes to the bin (`workflow-tables-bin.md`); a final delete takes the table row first: a request stopped halfway leaves nothing reachable.
- Results are codes (`not_found` 404, `forbidden` 403, `too_many_*` 409, fields 422).

## How do the privacy paths see person and record-link cells?
- In JSON, so the privacy export and erasure read them with `pg_privacy_wtable_rows()` (`includes/fn/privacy.php`, `core/personal-data-erasure.md`): the export hands over the cells naming the person, the erasure empties them and keeps the row.
- A hit: a person cell with their account, a link of a kind in `pg_privacy_wtable_link_kinds()` (contact, user_account, a person erp_account) to theirs; a deleted column's leftover cell is read as either. SQL `LIKE '%:id,%'`/`'%:id}%'` narrows, PHP decides (`pg_privacy_wtable_cell_hits()`); the erasure writes each row on its revision, re-read up to 3 times. `ws_wtable_row_closed_types()` is empty: both are written like any cell.
- A contact merge re-points contact links in the cells (`ws_wtable_cells` holder, `core/contact-holders.md`).
