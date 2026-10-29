---
name: decisions-made-schema
description: Decisions list, schema part: steps approved in advance (ENUM appends in the 4.48 shape), the tables and columns approved on 2026-10-27 and 2026-10-28, who decides schema
---

## Which schema steps are approved in advance?
- A payment driver's step appending a value to the `orders.payment_method` or `config.ecommerce_payment_gateway` ENUM in the 4.48 shape (#2389, 2026-10-27): `install_column_info()`, append at the end, keep nullability and default, skip when present or not an ENUM; appending rebuilds no table, a narrowing MODIFY stays forbidden (`release/migration-step-helpers.md`). First use `'PayPal Checkout'` (11.0.50); later drivers ask nothing new.

## Which schema was approved on 2026-10-27?
- Product bundles (owner, #2425): `products.bundle`, `bundle_pricing`, `bundle_discount_bp`; the tables `product_bundle_items`, `order_item_components`, `erp_invoice_item_components`; the `erp_stock_moves` unique key becomes `(kind, line_id, product_id)`, reading the old shape first.
- Collections (#2393, 11.0.43): `custom_form_pages.collection` TINYINT(1), `form_fields.collection_kind` VARCHAR(32), `form_fields.reference_page_id` INT UNSIGNED: pickers, lists, feeds, filters and the API find a collection and a field's kind and target without the design tree; VARCHAR, so a new kind needs no migration.
- Scheduled actions (#2396): `ws_scheduled_actions.anchor` VARCHAR(12) NOT NULL DEFAULT '' (`''` a plain action, `hidden` a flow's own trigger box, `converted` a former action run through a flow), added after the flows tables' steps; the same step marks today's hidden anchors (`flow_id <> 0`) with a re-runnable UPDATE; a VARCHAR, so a later value needs no MODIFY.
- Team-device pairing (#2470, #1640): `api_device_pairings` (app, user, `code_hash` CHAR(64) UNIQUE, created/expires/used timestamps, device, `created_ip`; index `user_id`) by `install_create_table()` on a free API label. API expansion is the architect's (owner's standing rule, 2026-10-27).
- Paraşüt payment account (#2440): one non-VARCHAR `config` column naming the collection account (empty = today's behaviour); when set, the order's paid moment posts a payment on its sales invoice.
- Voice messages V3 (#2417): transcripts in `ws_voice`; a `ws_channels` column in the `ai_access` shape (private channels closed until an administrator allows).
- SEO and record-page settings (#2471-#2475): three `config` and two `custom_form_pages` columns; behaviour: `decisions-made-seo.md`.

## Which schema was approved on 2026-10-28 and later?
- `decisions-made-schema-2.md` (2026-10-28), `decisions-made-schema-3.md` (2026-10-29 on).

## Who decides schema?
- The owner (CLAUDE.md fixed rule 5): the architect asks a schema question as a selectable list and records the answer (`workflow/architect.md`). The 2026-10-27 steps: architect, confirmed by the owner on 2026-10-28; reasons on the issues.
