---
name: decisions-made-schema
description: Decisions list, schema part: steps approved in advance (ENUM appends of payment methods and gateways in the 4.48 shape), the collections columns, the scheduled-action anchor mark
---

## Which schema steps are approved in advance?
- A payment driver's step appending a value at the end of the `orders.payment_method` or `config.ecommerce_payment_gateway` ENUM in the 4.48 shape (#2389, 2026-10-27): read the column with `install_column_info()`, append at the end, keep nullability and default, skip when the value is there or the column is not an ENUM. Appending changes no stored value and rebuilds no table; a MODIFY that narrows an ENUM stays forbidden (`release/migration-step-helpers.md`). First use: `'PayPal Checkout'` (11.0.50); PayTR and later drivers ask nothing new.

## Which schema was approved on 2026-10-27?
- Product bundles (owner, #2425): `products.bundle`, `bundle_pricing`, `bundle_discount_bp`; the tables `product_bundle_items`, `order_item_components`, `erp_invoice_item_components`; the `erp_stock_moves` unique key becomes `(kind, line_id, product_id)`, reading the old shape first. The design: `plans/storefront-bundles-design.md`; step 1 may be claimed.
- Collections (#2393, 11.0.43): `custom_form_pages.collection` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `form_fields.collection_kind` VARCHAR(32) NOT NULL DEFAULT '', `form_fields.reference_page_id` INT UNSIGNED NOT NULL DEFAULT 0: pickers, lists, feeds, filters and the API find a collection and read a field's kind and target without loading the design tree; VARCHAR, not ENUM, so a new kind needs no migration. `record_revisions` (C7) is asked when its step comes.
- Scheduled actions (#2396): `ws_scheduled_actions.anchor` VARCHAR(12) NOT NULL DEFAULT '' (`''` a plain action, `hidden` a flow's own trigger box, `converted` a former action run through a flow), added in the open version after the flows tables' steps; the same step marks today's hidden anchors (`flow_id <> 0`) with a re-runnable UPDATE. Filters stay plain SQL; a VARCHAR, so a later value needs no MODIFY.

## Who decided these?
- The architect, under the owner's rule of 2026-10-27 that only big architectural changes are asked (`workflow/architect.md`); the reasons are on the issues.
