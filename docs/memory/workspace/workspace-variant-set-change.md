---
name: workspace-variant-set-change
description: A variant set added from a change card (variant_set, PR #1943): fields, variants and price_rules values, checks, writer, events, matrix card, proposers
---

## What is it?
- `variant_set` in `ws_change_types()` (includes/workspace/changes.php): one 'select' product group and a product per row, action `create` only, right `ecommerce`, tag `product_group`, table `product_groups`. Once made it is a group and its products, changed as those.
- Proposed like every kind: an application through `workspace.changes.propose` or its task twin (no route of its own), Claude in its answer's `changes`, Pinegrap AI with `propose_change` (variants and price_rules as JSON lists). The person applies it on the card.

## Which fields?
- `name`, `short_description` (the group's name when given), `full_description`, `enabled` (off sale unless true); create-only `parent_id` (one group), `price` (the base, minor units), `price_rules`, `variants`.
- `variants` (`ws_change_variant_rows()`, pure, limit [100 rows, 100 characters]): objects of name (SKU), short_description, gtin, attributes ({attribute_id, option_id}, one per attribute), price (whole minor units, `pg_catalog_variant_set_minor_units()`; left out: priced by the rules), quantity (0..1000000, tracks the stock). Never rounded; a SKU twice refused. Refusals name `variants[i].field`.
- `price_rules` (`ws_change_price_rules()`, pure): the list POST /product-groups/variant-sets takes; amounts whole ints (the normaliser would round), then `pg_variant_price_rules_normalize()`; kept as its `attributes`.

## What is checked?
- `ws_change_create_check()`: name and variants. `ws_change_variant_set_refusal()` from `ws_change_check()` (proposal and apply): group name and SKUs unused, one parent, option pairs this shop's, a row without a price needs rules, rules owned (`pg_variant_price_rules_owned()`), then `pg_catalog_variant_set_check()` on `ws_change_variant_set_data()`.

## How is it written?
- `ws_change_write_variant_set()`: `pg_catalog_variant_set_create()` with the applier, `submit_form` empty, `barcode_auto` false; the starting row of a Workspace product (`ws_change_variant_set_data()`); no parent: `pg_catalog_group_top_id()` (parent 0 is a second root no shop page, search or feed reads). Then, as the Workspace's own product and group creates: `pg_audit_record()` per product (product_groups is not audited), `pg_catalog_seo_rescore()` per product and the group, `product.created` per product and `product_group.created` once (the set writer itself announces nothing: commerce/catalog-variant-set-writer.md). Decision tag `<#product_group:id>`.

## How is it shown?
- Card: the variants field carries `matrix` (`ws_change_variant_set_matrix()`, labels from `ws_change_variant_set_labels()`): one column per attribute in the rows' order, the SKU, the price each row gets (`pg_catalog_variant_set_price()`, so `pg_variant_price_compute()` on the base unless the row has its own; shown with `ws_money_out()`), a pencil on a typed price. The rules row is left out: they are in the prices. workspace.js draws the table (`field.matrix`).
- Decision and API `to_shown`: the SKUs; the rules as how many attributes set the prices.
- Pinegrap AI's guide lists the shop's attributes and options with ids (`ws_ai_variant_attributes_guide()`, 20 × 40): it reads attributes nowhere else. Applications read them with GET /product-attributes.

## What is left out on purpose?
- Not a scheduled action's change or rule record (`ws_scheduled_change_skipped()`, `ws_scheduled_record_types()`), so no line in `workspace_flows_parity_table()` (create-only kinds are not mapped, as erp_quote). Not a record kind: an exception in `workspace_record_kinds_registry_test_lists()`.
- No images, barcodes, zones or form on a set from a card; the product screen does them.

## What guards it?
- tests/workspace_variant_set_change_test.php; tests/catalog_group_writer_test.php (its announcement counted apart).
