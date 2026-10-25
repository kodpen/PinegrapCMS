---
name: workspace-catalog-create
description: Plan (2026-10-23, 1a helper in #1512, 1b built, answers 1 and 3 given): Workspace product and group creates; one group writer; bulk drafts (list, xlsx, AI); variant sets in one go
---

## What is asked? (owner, 2026-10-23)
- Create products and groups from the Workspace, many at once with AI help.

## What is there today?
- Only assistants propose creates (ai.php, api_claude.php): `ws_change_create_check()` (name needed, free); `ws_change_write_product()` → `pg_pb_create_product()`: off sale, `product.created`, scored (`pg_catalog_seo_rescore()`, 1b). Drawer, /price, /stock, bulk.php never create.
- Group creates (copies, templates aside): add_product_group.php; `api_product_groups_create()`, `ws_change_write_product_group()` (both through `pg_catalog_group_create()` since 1b: scored, `product_group.created` once; `commerce/catalog-group-writer.md`); `pg_pb_create_group()` (variant set).
- `ws_change_types()` limits over the varchar, cut silently: group `short_description`, `keywords` 2000 (100, 255); page `meta_description` 1000 (255); calendar_event `name`, `short_description`, `location` 255, 1000, 255 (100, 255, 100).
- A variant set ('select' group, attribute axes, price rules): only add_product.php (`pg_pb_save_new_product()`); the API makes 'browse' groups.
- Right: `ws_viewer()['ecommerce']` (store on; role ≤ 2 or `user.user_manage_ecommerce`), as add_product.php, `products:write`: none new.

## What is proposed? (not approved yet)
- Every Workspace create goes through changes.php, off sale unless ticked; a person's create is an applied `ws_ai_changes` row (request_id 0). add_product*.php, import_products.php stay.

## Slices
None needs schema.
1a. Each `ws_change_types()` limit set to its column (six above); new tests/workspace_change_limits_test.php (setup SQL, as tests/api_text_bounds_test.php) on a shared helper reading `ws_change_types()` from source (it calls `*_ready()` probes), also for CRM slice 1. Helper #1512; limits left.
1b. Built: `pg_catalog_group_create()`, `pg_catalog_group_clouds()` (ecommerce.php) behind the API and Workspace twins; SEO scored, announced once (Q3).
2. "New product / product group" in the drawer (`openRecord()` new mode, assets/js/workspace.js), the palette and `/product` (`/ürün`, /price-like preview; a proposed `ws_commands()` entry after plans/workspace-slash-commands.md slice 2, `ws_command_names()` unchanged); new `ws_quick_create_save()` (quick_edit.php) and panel action `ws_record_create`; decision tick. API: POST /products, /product-groups suffice (owner default for quick edit, 2026-10-19).
3. One 'bulk' `ws_ai_changes` row (action create, `rows`), checked at proposal and write, run in small `ws_bulk_step()` slices (SEO rescored per row: `pg_seo_recalculate()`), done rows kept: no double on resume. From a pasted list, a channel xlsx/csv ("Make products", as the import: `pg_products_import_to_csv()`, `pg_products_import_price_cents()`), Pinegrap AI / @Claude from a description (Premium like Pinegrap AI? plans/workspace-agent-handles.md Q2). API: the answer routes' `bulk`; MCP: one 'bulk' create entry via `workspace.changes.propose` after plans/workspace-mcp-capabilities.md slice 3, no own route.
4. One-go variant set: new `pg_catalog_variant_set_create()` (ecommerce.php) out of `pg_pb_save_new_product()` (screen unchanged), kind `variant_set`: a matrix card priced by `pg_variant_price_compute()`; new `POST /product-groups/variant-sets` (products:write, dry_run), offered over MCP per the MCP plan's question on direct AI writes.

## Open questions (recommendation)
1. Answered (a): add_product_group.php keeps its own code.
2. Rows per bulk card: 100, 200, `WS_BULK_MAX` 1000 → 200; more: import_products.php.
3. Answered yes: Workspace group creates announce `product_group.created` (1b).
4. Bulk card events: (a) `product.created` per row, as AI bulk updates, (b) none, as imports → a.

## Where to start?
- Slice 1a limits (a fix, no decision), then 2.
