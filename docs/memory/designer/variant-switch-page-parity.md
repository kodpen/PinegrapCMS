---
name: variant-switch-page-parity
description: A picked variant shows what its page shows - an offer's strike-through price block (get_product price_block_html), the short description as photo alt, a rich text's value inside its markup
module: widgets_catalog
---

## What changed?
- Price: the payload's `price_formatted` and `price_range` are `_civ_price_shown()` (`includes/fn/widgets_catalog.php`): the strike-through block of `_pg_format_price_with_discount()` while an offer lowers the price, else the amount. The page's `__price_formatted` and `__price_range` use the same helper. `price_block_html` is the block either way, as `__price_block_html`. Before, a switch showed the plain amount and left `price_range` and `price_block_html` showing the first variant.
- Alt text: `_civ_image_alt()` gives the payload's `image_alt` and the page's `__image_alt`: the short description (the name the store shows), else the Product ID / SKU (`name`), as the cart's `__item_image_alt`. Before, the page printed the SKU and a switch the short description. Owner decision of 2026-11-10 ([[decisions-made-product-40]]). The catalog cards' `__image_alt` is still the SKU: not changed here.
- Markup around a bound word: kept on a switch (below).

## Why does get_product send price_block_html?
- The refresh after the load and after each pick (`refreshFromApi()`) wrote `get_product`'s `price_formatted`, the plain amount, over the block. `get_product` is a public answer old custom layouts read, so `price_formatted` keeps the amount and `price_block_html` is added; the script writes the block where `price_formatted` and `price_range` are bound while `has_discount` is `'1'`. Both keys are in `HTML_FIELDS` ([[variant-switch-text-values]]).

## How does a value keep the markup around it?
- The server puts a bound rich text's value in place of the one run of words its markup wraps (`pg_binding_text_slot()`, [[data-binding-to-content]]). `textSlot()` (`assets/js/pg_civ_variants.js`) finds that place on the page for a `text` binding of a plain-text key: the one text node with words (PHP's `trim()` set; `&nbsp;` is a space) inside an element that holds markup.
- That node must show the value the page was drawn with (the payload's `default_product_id` variant; its line breaks compared as `\n`, as the browser reads them), so a child's words (a badge in a link) are never overwritten.
- The node is kept on the element (`pgCivTextSlot`) with the spaces around the words: an empty value leaves an empty text node there, and the next value goes back in.

## What still takes the whole content?
- A key of `HTML_FIELDS`: the drawn value's own tags cannot be told from the design's.
- An element drawn with the value empty (no words show the place), or with several runs of words (children with words), as before.

## What holds it?
- `tests/civ_variants_text_test.php` (Node, a fake page with text nodes): the block on a pick and on the refresh, a link kept around the name, an emptied brand keeping its place, the drawn-empty case; `_civ_price_shown()`, `_civ_image_alt()`, the stamp (12).
- `tests/storefront_get_product_enabled_test.php`: `price_block_html` with an offer, `price_formatted` unchanged.
