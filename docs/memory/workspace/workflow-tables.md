---
name: workflow-tables
description: Workflow tables (11.0.110, #2746): the four ws_wtable* tables column by column, ws_wtable_access(), column changes; JSON person/link cells closed until the erasure reads them
---

## What are the four tables, column by column?
- `ws_wtables`: `user_id` owner; `name` (120 characters checked in PHP); `description` (2000); `row_count` so the 5000-row limit needs no `COUNT(*)`; `revision` bumped on every column change (tells a screen to reload the columns; a row write is checked against the columns as they are then, not against this); `created_at`, `updated_at`. `idx_owner (user_id, updated_at)` lists "my tables" and counts them for the 500 limit.
- `ws_wtable_columns`: `table_id`, `name`, `type` VARCHAR(16) (one of `ws_wtable_column_types()`), `options` JSON (decimals, currency, choices, kinds, expression), `position`, `created_at`. `idx_table (table_id, position)`.
- `ws_wtable_rows`: `table_id`, `position`, `cells` MEDIUMTEXT JSON object by column id (money integer minor units), `revision` (409 `changed_meanwhile`), `created_by/at`, `updated_by/at`. `idx_table (table_id, position)`.
- `ws_wtable_shares`: `ws_flow_shares` column for column: `table_id`, `target_type` user|channel, `target_id`, `level` view|edit (channel: view only), `created_by/at`; `UNIQUE (table_id, target_type, target_id)`, `idx_target`.
- All InnoDB utf8mb4, no ENUM (a later type or share kind needs no MODIFY), no foreign keys: `ws_wtable_delete()` removes columns, rows and shares.

## Why JSON cells and not a row per cell?
- A row write stays one `UPDATE … WHERE revision = ?`; a column delete needs no mass DELETE. Option (c), a row per cell, was refused in #2746.
- No MySQL JSON functions: MariaDB 10.3 and MySQL 5.7 read JSON differently; filter and sort in PHP. The limits (50 columns, 5000 rows, 500 tables, `ws_wtable_limits()`) bound a table's read.

## Who reaches a table?
- `ws_wtable_access()` → owner | edit | view | '' (`ws_wtable_access_level()` is the pure rule, #2424): the owner; a person shared at view or edit; a channel share gives view to the channel's readers. No role gives access: an administrator reaches a personal table only through a share.
- Owner gone (no user row, `ws_profiles.excluded`, no workspace right: `ws_rights_for_id()['member']` false) → nobody reaches it, shares included. Its flows pause (later slice).
- `ws_wtable_may()`: read view+, write edit+ (name, description, columns, rows), manage owner only (delete, shares).

## How do column changes behave?
- A column's type is fixed once made (`type_fixed`): written cells belong to it. Name and options may change.
- A deleted column's cells stay in the rows' JSON until the row's next write (`ws_wtable_cells_merge()` drops columns the table lacks); reads skip them. Column ids are never reused.
- A table delete is final (no bin yet): the table row goes first, so a request stopped halfway leaves nothing reachable.
- Results are codes (`not_found` 404, `forbidden` 403, `too_many_*` 409, field errors 422) for the panel and API to word.

## What must the person and record-link cells get before they can be filled?
- They live in JSON, so `pg_contact_holders()` cannot re-point them and the privacy export and erasure (`core/personal-data-erasure.md`) do not see them. Until those paths get a reader for `ws_wtable_rows.cells`, a row write refuses a non-empty cell of either type (`not_writable_yet`, `workflow-table-rows.md`).
