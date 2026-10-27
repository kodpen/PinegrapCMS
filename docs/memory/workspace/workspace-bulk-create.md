---
name: workspace-bulk-create
description: Bulk product drafts (bulk.php action create, PR #2266): one 'bulk' card of up to 200 rows, checks at proposal and write, row place saved before the write, events, card
---

## What is it?
- A `ws_ai_changes` row with action `bulk` whose spec has `action` `create` and `rows`: one object of field => value per new product. Products only (`ws_bulk_create_types()`). Proposed in the `bulk` object of the answer routes and `workspace.changes.propose`: `{type: "product", action: "bulk", bulk: {action: "create", rows: [...]}}`. No route of its own.

## How many rows?
- At most 200 (`WS_BULK_CREATE_MAX`); more is refused with a pointer to `import_products.php`. Owner decision 2026-10-27 (`workflow/decisions-made-workspace.md`). The rows together stay under 4 MB (`WS_BULK_CREATE_BYTES`) of the MEDIUMTEXT `fields` column.

## What is checked, and when?
- At proposal (`ws_bulk_create_input()`): `ws_bulk_create_rows()` (no database: fields the single create takes, `ws_change_value()` shapes, a name on each row, no name twice case-folded), then per row `ws_change_create_check()` (name not taken) and `ws_change_check(…, 'create', …)`. A refusal names the row (`rows[3]: …`).
- At the write (`ws_bulk_create_row()`): rights (`ws_change_allowed()`), `ws_change_create_check()` and `ws_change_check()` again, since a name may be taken after the card was made; a refused row counts as failed with its reason ("row 4: …").

## Why is a row never made twice?
- `ws_bulk_step()` runs 10 rows a slice (`WS_BULK_CREATE_STEP`) under the change's `GET_LOCK`. Each row's place (`pos`, counted before the row) is saved with `ws_bulk_save_state()` before `ws_change_write()`. A request that dies after the write resumes after that row; at worst the row is made but not counted, never made twice. Test: tests/workspace_bulk_create_test.php reads the order from source.

## What does each product get?
- The single create's writer (`ws_change_write_product()` → `pg_pb_create_product()`): off sale unless the row ticks `enabled`, SEO scored (`pg_catalog_seo_rescore()`), `product.created` per row (owner decision 2026-10-27, as the assistants' bulk updates send their events), audit line as a job.

## How is the card drawn?
- Label "New records in bulk"; summary "Make N new records (Product), off sale unless a row puts it on sale"; sample: the first five names with their price; `bulk.create` true picks the create warning and result line in workspace.js. The decision reads "N records made, N left out, N could not be made" with up to 30 product tags.

## What is not built yet?
- Plan workspace-catalog-create 3b: rows from a pasted list and a channel xlsx/csv. 3c: Pinegrap AI's `propose_bulk_change` tool and @Claude texts that teach `action` create (the AI tool still copies only the update keys, so it cannot pass `rows`).
