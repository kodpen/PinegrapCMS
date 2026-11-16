---
name: catalog-detail-variant-price-blocks
description: Catalog detail closed blocks a variant switch redraws (CB6a2): the price block and the Variant Attribute node drawn on the canvas as the site prints them, settings only, why neither is a tree
---

## What did CB6a2 open?
- The second part of step CB6a (`plans/designer-closed-blocks-steps.md`); the first is [[catalog-detail-open-blocks]].
- Price block (`__price_block_html`) and Variant Attribute (content node `variant_attr`): settings only, drawn true on the canvas. Both depend on the product, its variants and the shopper's pick.

## Why is neither a tree?
- The variant switch (`assets/js/pg_civ_variants.js`) writes the whole price block into the element bound to it (`price_block_html` is in `HTML_FIELDS`, [[variant-switch-text-values]]): elements inside it would be overwritten on the first pick.
- The script finds the variant controls by what the server builds: `.pg-civ-variant-attr` with `data-pg-civ-attr`, the hidden mirror select of the radio and button styles, `data-pg-civ-attr-mirror`. A designer's tree could drop one and stop the switch; the system owns what a control sends ([[widget-designability-decisions-2]]).

## What does the canvas draw?
- An element bound to `__price_block_html`: `_sdPriceBlockHtml(500, 450)` in `_SD_HTML_PREVIEW_TOKENS`, the block `_pg_format_price_with_discount()` prints for the sample product an offer lowers: the sticker price struck through in red, the price paid in green. `pg_carousel_layout.css` is not on the canvas, so its look is inline. Before, the canvas showed one plain amount.
- A Variant Attribute node in a catalog widget: `_sdVariantAttrHtml()`, the twin of `_civ_expand_variant_attr_markers()`, for Colour (Red, Blue, Green) and Size (S, M, L), the first of each picked, in the node's style and classes (`_sdVariantAttrCfg()`, also what `toHTML()` puts in the marker). Inert, `sd-editor-ph`. Outside a catalog widget the old box naming the style stays.
- `__price_formatted` keeps its plain sample: it prints the block only while an offer applies.

## Does a running site change?
- No: the saved marker and the tokens are as before; the switch is unchanged.

## How was it checked?
- `tests/catalog_detail_closed_blocks_test.php`: the variant attribute twin byte for byte against `_civ_expand_variant_attr_markers()` for every style and class field, the marker's settings, the price block's elements and classes against `_pg_format_price_with_discount()` with and without an offer.
- Sandbox: the Online Store template's product page with a variant group, canvas before and after, the site page with a variant picked.
