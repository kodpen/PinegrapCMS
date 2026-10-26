---
name: workspace-catalog-create
description: Plan (2026-10-23, 1a #1603, 1b #1604, 2 #1613, answers 1 and 3 given): Workspace product and group creates; one group writer; bulk drafts (list, xlsx, AI); variant sets in one go
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
3. One 'bulk' `ws_ai_changes` row (action create, `rows`), checked at proposal and write, run in small `ws_bulk_step()` slices (SEO rescored per row: `pg_seo_recalculate()`), done rows kept: no double on resume. From a pasted list, a channel xlsx/csv ("Make products", as the import: `pg_products_import_to_csv()`, `pg_products_import_price_cents()`), Pinegrap AI / @Claude from a description (Premium like Pinegrap AI? plans/workspace-agent-handles.md Q2). API: the answer routes' `bulk`; MCP: one 'bulk' create entry via `workspace.changes.propose` after plans/workspace-mcp-capabilities.md slice 3, no own route.
4. One-go variant set. 4a (PR #1887): `pg_catalog_variant_set_create()` and `POST /product-groups/variant-sets` (`commerce/catalog-variant-set-writer.md`). 4b: the `variant_set` change kind and matrix card (changes.php, ai.php, workspace.js). Was: new `pg_catalog_variant_set_create()` (ecommerce.php) out of `pg_pb_save_new_product()` (screen unchanged), kind `variant_set`: a matrix card priced by `pg_variant_price_compute()`; new `POST /product-groups/variant-sets` (products:write, dry_run), offered over MCP per the MCP plan's question on direct AI writes.

## Open questions (recommendation)
1. Answered (a): add_product_group.php keeps its own code.
2. Rows per bulk card: 100, 200, `WS_BULK_MAX` 1000 → 200; more: import_products.php.
3. Answered yes: Workspace group creates announce `product_group.created` (1b).
4. Bulk card events: (a) `product.created` per row, as AI bulk updates, (b) none, as imports → a.

## Where to start?
- Slice 3, or 4b.
