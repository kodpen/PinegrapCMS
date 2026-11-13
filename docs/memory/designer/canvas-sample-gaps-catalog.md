---
name: canvas-sample-gaps-catalog
description: CS0 census, catalog: what the catalog listing, product page, wishlist, order form, search, gallery, tag cloud and folder view still draw without a sample after CS4a (#9253)
---

## What did CS4a settle?
- Every catalog, product, entity, wishlist and order form value draws the sample product (`canvas-sample-product.md`); the category tree, filters and variant picker draw controls; the tag cloud and the search empty message have samples. On the Market template's product page no value draws a ‹label› any more.

## What is left?
- Every bound picture (`__image_url`, `__thumbnail_url`, `__gallery_image_1` to `_5`, `product.images`, related and bundle pictures, `__result_image`, `__gallery_item_image_url`) is the grey `[token]` box (CF4 of `plans/designer-canvas-faults.md`).
- The listing's cards after the first are the site's real records (`designer-widget-ghosts-and-edit-chips.md`): a shop with no products shows one card.
