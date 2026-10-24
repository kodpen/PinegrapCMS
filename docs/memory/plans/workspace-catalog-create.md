---
name: workspace-catalog-create
description: Plan (2026-10-23, not started, answers open): a person creates products and groups in the Workspace; one group writer; bulk drafts from a list, xlsx or AI; variant sets in one go
---

## What is asked? (owner, 2026-10-23)
- Create products and product groups from the Workspace, many at once with AI help.

## What is there today?
- Only assistants propose a create (ai.php, api_claude.php): `ws_change_create_check()` (name needed, free); `ws_change_write_product()` → `pg_pb_create_product()`: off sale, `product.created`, no SEO score (API: `api_products_rescore()`). The drawer, /price, /stock and bulk.php only edit.
- Group creates (copies, templates aside): add_product_group.php; `api_product_groups_create()` and `ws_change_write_product_group()` (same row; cloud twins `api_product_group_clouds()`, `ws_change_group_clouds()`; only the API announces `product_group.created`; no SEO score); `pg_pb_create_group()` (variant set).
- `ws_change_types()` limits over the varchar, cut in silence: group `short_description`, `keywords` 2000 (100, 255); page `meta_description` 1000 (255); calendar_event `name`, `short_description`, `location` 255, 1000, 255 (100, 255, 100).
- A variant set ('select' group, attribute axes, price rules) comes only from add_product.php (`pg_pb_save_new_product()`); the API makes 'browse' groups.
- Rights: `ws_viewer()['ecommerce']` (store on; role ≤ 2 or `user.user_manage_ecommerce`), as add_product.php and `products:write`: no new right.

## What is proposed? (not approved yet)
- Every Workspace create goes through changes.php, off sale unless ticked; a person's create is an applied `ws_ai_changes` row (request_id 0). add_product*.php, import_products.php stay.

## Slices
1a. No schema: each `ws_change_types()` limit set to its column (the six above); new tests/workspace_change_limits_test.php on the setup SQL like tests/api_text_bounds_test.php.
1b. No schema: new `pg_catalog_group_create()`, `pg_catalog_group_clouds()` in includes/fn/ecommerce.php for API and Workspace, twins wrap them; Workspace product and all group creates score SEO; announcing waits for Q3.
2. No schema: "New product / product group" in the drawer (`openRecord()` new mode, assets/js/workspace.js), the palette and new `/product` (`/urun`) with a /price-like preview; new `ws_quick_create_save()` (quick_edit.php) and panel action `ws_record_create`; decision tick. API: none new (POST /products, /product-groups), as for the quick edit (owner default 2026-10-19).
3. No schema: bulk create: one 'bulk' `ws_ai_changes` row (action create, `rows`), rows checked at proposal and write, sliced by `ws_bulk_step()`, done rows kept: a resume never doubles. Sources: a pasted list, a channel xlsx/csv ("Make products"; read like the import: `pg_products_import_to_csv()`, `pg_products_import_price_cents()`), Pinegrap AI / @Claude from a description. API: the answer routes' `bulk`; new `POST /workspace/channels/{id}/product-drafts` (workspace:write, dry_run, the card's check): an MCP client lays a card a person applies.
4. No schema: variant set in one go: new `pg_catalog_variant_set_create()` (ecommerce.php) out of `pg_pb_save_new_product()` (screen unchanged), a new `variant_set` create kind, a matrix card priced by `pg_variant_price_compute()`; new `POST /product-groups/variant-sets` (products:write, dry_run, an MCP tool).

## Open questions (recommendation)
1. Shared group writer in add_product_group.php too? (a) no, (b) yes → a.
2. Rows per bulk card: 100, 200, `WS_BULK_MAX` 1000 → 200; more: import_products.php.
3. Workspace group create announces `product_group.created` (now "through the API") → yes.
4. MCP `products_create` writes at once: (a) keep, drafts optional, (b) drafts only → a.
5. Bulk card events: (a) `product.created` per row, like AI bulk updates, (b) none, like imports → a.

## Where to start?
- Slice 1a (a fix, no decision), then 1b, 2. Each slice writes its memory file.
