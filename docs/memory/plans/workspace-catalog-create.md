---
name: workspace-catalog-create
description: Plan (2026-10-23, 1a #1603, 1b #1604, 2 #1613, answers 1-4 given; 3a #2266): Workspace product and group creates; one group writer; bulk drafts (list, xlsx, AI); variant sets in one go
---

## What is asked? (owner, 2026-10-23)
- Create products and groups from the Workspace, many at once with AI help.

## What is there today?
- Assistants propose creates (ai.php, api_claude.php): `ws_change_create_check()` (name needed, free); `ws_change_write_product()` → `pg_pb_create_product()`: off sale, `product.created`, scored (`pg_catalog_seo_rescore()`, 1b). A person creates from the drawer's new mode, the palette and /product (slice 2, workspace/workspace-quick-create.md); bulk.php never creates.
- Group creates (copies, templates aside): add_product_group.php; `api_product_groups_create()`, `ws_change_write_product_group()` (both through `pg_catalog_group_create()` since 1b: scored, `product_group.created` once; `commerce/catalog-group-writer.md`); `pg_pb_create_group()` (variant set).
- A variant set ('select' group, attribute axes, price rules): only add_product.php (`pg_pb_save_new_product()`); the API makes 'browse' groups.
- Right: `ws_viewer()['ecommerce']` (store on; role ≤ 2 or `user.user_manage_ecommerce`), as add_product.php, `products:write`: none new.

## What is proposed? (not approved yet)
- Every Workspace create goes through changes.php, off sale unless ticked; a person's create is an applied `ws_ai_changes` row (request_id 0). add_product*.php, import_products.php stay.

## Slices
None needs schema.
1a. PR #1603: every `ws_change_types()` text limit fits its column but the channel summary (kept); tests/workspace_change_limits_test.php (setup SQL, #1512 source reader). See workspace/workspace-change-text-limits.md.
1b. PR #1604: `pg_catalog_group_create()`, `pg_catalog_group_clouds()` (ecommerce.php) behind the API and Workspace twins; SEO scored, announced once (Q3).
2. PR #1613: "New product / product group" in the drawer (`openRecord()` new mode), the palette, `/product` (`/ürün`, `ws_command_create_names()`, `ws_command_names()` unchanged); `ws_quick_create_save()`, panel action `ws_record_create`; decision tick. API: POST /products, /product-groups suffice (owner default, 2026-10-19).
3. Bulk product drafts, cut in three (each ~1,000 lines):
   3a. PR #2266: one 'bulk' `ws_ai_changes` row with `bulk.action` create and `rows` (products only, `WS_BULK_CREATE_MAX` 200, Q2), checked at proposal (`ws_bulk_create_rows()`, names free and not twice) and again per row at the write; run by `ws_bulk_step()`, each row's place saved before its write (`ws_bulk_create_row()`): no double on resume. Off sale unless the row ticks `enabled`; scored and `product.created` per row by the product writer (Q4). Reaches the answer routes' and `workspace.changes.propose`'s `bulk` with no new route. (`workspace/workspace-bulk-create.md`).
   3b. From a pasted list and a channel xlsx/csv ("Make products", as the import: `pg_products_import_to_csv()`, `pg_products_import_price_cents()`): rows proposed as a 3a card.
   3c. Pinegrap AI / @Claude from a description (Premium like Pinegrap AI? plans/workspace-agent-handles.md Q2): the tool texts that teach `bulk.action` create; MCP: the same entry via `workspace.changes.propose` after plans/workspace-mcp-capabilities.md slice 3.
4. One-go variant set. 4a (PR #1887): `pg_catalog_variant_set_create()` and `POST /product-groups/variant-sets` (`commerce/catalog-variant-set-writer.md`). 4b (PR #1943): the `variant_set` change kind and matrix card (workspace/workspace-variant-set-change.md).

## Open questions (recommendation)
1. Answered (a): add_product_group.php keeps its own code.
2. Answered (owner, 2026-10-27, «toplu ürün önerin kabul»): 200 rows per bulk card; more: import_products.php.
3. Answered yes: Workspace group creates announce `product_group.created` (1b).
4. Answered (same answer): (a) `product.created` per row, as AI bulk updates.

## Where to start?
- Slice 3b after #2266 merges, then 3c.
