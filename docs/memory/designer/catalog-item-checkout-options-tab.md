---
name: catalog-item-checkout-options-tab
description: The product page starter's Checkout Options tab (catalog_item_view) is bound to the store's switched-on payment methods, with real fees, or not drawn (#4556); saved trees untouched
module: widgets_catalog
---

## What was wrong?
- The `catalog_item_view` starter tree (`assets/js/style_designer.src.js`, "Product Information (Tabs)") carried a static "Checkout Options" table: four sample methods and a "+9.90 TL" cash-on-delivery fee. The Online Store, Boutique and Bookshop product pages showed it whatever the store's payment settings said. A fee shown to buyers must not be invented.

## How does it work now? (#4556, PR #PRNUM)
- The starter's table body (`tbody`) carries `_bindings.section = payment_methods` (Section list of catalog_item_view). `_render_system_widget_catalog_item_view()` calls `_pg_civ_fill_payment_methods($tree_decoded, pg_civ_payment_method_rows(_pg_civ_payment_settings()))` before the comments frame drop and the split.
- Rows (pure `pg_civ_payment_method_rows()`): card when the card switch is on and a gateway is set (fee = `ECOMMERCE_SURCHARGE_PERCENTAGE` as `{var:1}%`, "0%" when none), instalments under the card when the gateway is Iyzipay and `ECOMMERCE_IYZIPAY_INSTALLMENT` >= 2, bank transfer when `ECOMMERCE_OFFLINE_PAYMENT` is on, cash on delivery when offline is on and `pg_sms_code_on('cod')`, PayPal Express, Pay with iyzico, on account when `pg_erp_open_account()` is not null. Every fee cell but the card's is empty: no fee is invented.
- Each row is a copy of the body's first `tr` (the designer's look) with each cell's first content node set (escaped); no `tr` to copy: a plain three-cell row.
- No rows (e-commerce off or nothing on): `_pg_civ_drop_frames()` (the generalised `_pg_civ_drop_comments_frame()`, which now calls it with `_pg_civ_is_comments_block`) drops the pane, its trigger and its li; the first tab left opens.
- Canvas: `sdDesign.siteFlags.payment_methods` (`designer_screen.php`, on account counted by its setting) feeds `_sdCatalogBindingDisabled()` for catalog_item_view; the body fades with "Off — no payment method is switched on in the store settings". The section hint is not drawn inside table parts (it would break the table).
- Starter sample rows (canvas only): card and bank transfer, empty fee cells; header "Fee". Store, Boutique and Bookshop build their product page from the starter (`'tree' => 'starter'`), so only their version moved (1.2.1).
- Running sites: saved trees keep their static table, no migration; a page re-made from the starter or a template gets the bound one.
- Test: `tests/catalog_item_view_payment_methods_test.php`.

Related: docs/memory/designer/catalog-binding-disabled-no-output.md, docs/memory/designer/widget-starters.md, docs/memory/designer/comments-block-product-page.md
