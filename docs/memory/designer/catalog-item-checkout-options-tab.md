---
name: catalog-item-checkout-options-tab
description: The product page starter's Checkout Options tab (catalog_item_view) is bound to the store's switched-on payment methods, with real fees, or not drawn (#4556); saved trees untouched
module: widgets_catalog
---

## What was wrong?
- The `catalog_item_view` starter tree (`assets/js/style_designer.src.js`, "Product Information (Tabs)") carried a static "Checkout Options" table: four sample methods and a "+9.90 TL" cash-on-delivery fee. The Online Store, Boutique and Bookshop product pages showed it whatever the store's payment settings said. A fee shown to buyers must not be invented.

## What was decided? (#4556)
- Decided (architect, 2026-11-01): the tab is bound to the store's real settings, or not drawn.
- A section binding `payment_methods` on the pane's table node, like the checkout widget's `eo_section`: the server replaces its children with rows from the settings: card when the gateway is on (fee = `ECOMMERCE_SURCHARGE_PERCENTAGE`, "0%" when none), bank transfer when `ECOMMERCE_OFFLINE_PAYMENT` is on, cash on delivery when the SMS-code COD is configured (`includes/fn/sms_codes.php`), instalments when the gateway's instalment setting is on (the count), PayPal and iyzico when their gateway is the active one, on account only for a signed-in account that may buy on account. Row texts are fixed `lang()` strings; the fee column shows only a real figure.
- Empty (nothing on, or e-commerce off): the pane, its trigger and the trigger's `li` go and the first tab left opens: the comments-block frame drop (`_pg_civ_drop_comments_frame()`) generalised for any empty section. Designer mirror: `_sdCatalogBindingDisabled()` fades the node (`catalog-binding-disabled-no-output.md`).
- Starter and templates: the table keeps its look; the static rows stay only as the canvas sample, with no invented fee. The starter changes in its three places (`system-widget-change-checklist.md`), the three templates' product pages with a version bump.
- Running sites: saved trees keep their static table, no migration (template rows are never reused); a page re-made from the starter or the template gets the bound one.

Related: docs/memory/designer/catalog-binding-disabled-no-output.md, docs/memory/designer/widget-starters.md, docs/memory/designer/comments-block-product-page.md
