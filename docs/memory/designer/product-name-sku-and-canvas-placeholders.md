---
name: product-name-sku-and-canvas-placeholders
description: Which product token is the SKU and which the store name, their Bind Data labels and canvas samples; the placeholder of a value with no sample; a bound section's canvas name (#4200)
---

## Which product token is the name a shopper reads?
- `products.name` is the stock code the panel calls "Product ID / SKU" (`edit_product.php`); the name the store shows is `products.short_description`. So `__name`, `__item_name` (cart line), `__related_name` and `__bundle_item_name` carry the SKU, and the `…short_description` twins carry the product name. Token names never change.
- Until #4200 the Bind Data lists, the palette and the PHP list labels called the SKU "Product name" and the store name "Short description", so an operator who dropped "Product name" published the stock code (#3991). Now the SKU tokens read "Product ID / SKU" and the short-description tokens "Product name (shown in the store)" in every place the pair appears: the catalog, catalog detail, wishlist and order form palettes, the cart line, the related and bundle row lists (`pg_sw_list_product_sources()` and its JS twin; `tests/widget_lists_product_test.php`).
- Canvas samples follow (`_SD_SHORT_LABEL_TOKENS`): the SKU tokens preview as `PRD-1001`, the short-description tokens as "Sample Product Name", so a heading on `__short_description` no longer shows the product-description blurb.
- Not changed: order return lines (`__return_line_name`, `__return_item_name`) still say "Product name"; the order form starter's heading is bound to `__name`.

## What does a value with no sample show on the canvas?
- `_sdTokenPlaceholder(fieldName, node)`: the form field's label; else the label Bind Data gives the token in that widget (`_swBindLabel()`, translated); else a neutral ‹value›. It never builds an English label from the token name (‹Out of stock message›, ‹BRAND› in a Turkish panel, #3994).

## What name does a container bound to a section show?
- `_sectionLabels` in the canvas draw names the sections it knows; any other takes its name from the Section binding list (`_sdSectionOptions(type)` / `_sdSectionOptionLabel(type, sec)`, the same list the panel's select shows), and only an unknown key reads "Section: <key>". The name is escaped on both hint variants.

## Which Turkish words do the "filled by the backend" texts use?
- "sunucu doldurur" for backend, "sayfa çizilirken" / "çizilir" for render, "slayt" for slide, "tuval", "tekrar alanı" as in [[loop-band-canvas]]; set with `tools/tr_add.php --replace` (#4200).

## Which words do the Quick Order and cart section names avoid? (#4289)
- No file, table or column names (`view_offers.php`, `orders.discount_offer_id`) and no English left in the Turkish ("Attribute", "alert", "visitor", "Upsell", "LEGACY", "tree", "checkbox"): "Varyant Özelliği", "uyarı kutusu/şeridi", "ziyaretçi", "Ek satış teklifleri", "eski", "ağaç", "onay kutusu", "seçenek düğmeleri" for radios. A changed English source got a new key through `tools/tr_add.php`; Turkish-only fixes used `--replace`.
