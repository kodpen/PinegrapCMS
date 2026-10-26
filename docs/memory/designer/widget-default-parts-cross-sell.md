---
name: widget-default-parts-cross-sell
description: The catalog detail view's cross-sell default part (W16, #1973): the product.related cards that replaced __cross_sell_html, __has_related, where it is added, the variant script's server redraw
---

## What does the part draw?
- `pg_sw_part_catalog_cross_sell()` (`includes/fn/widgets_parts.php`): `div.pg-civ-cross-sell.mt-4` with `data-pg-civ-cross-sell="1"`, shown by `__has_related not_empty`; inside, by `__has_related eq 1`, `h3.h5.mb-3` "Frequently bought together" (translated when the part is made) and `div.row.g-3` holding a `product.related` list (`limit 4`) of `div.col-6.col-md-3` > `a.card…` (href `__related_url`).
- A card: an image node `card-img-top` (src `__related_image_url`, lazy) when it has one, else `div.card-img-top.bg-body-tertiary`; `div.card-body.p-2` with the short description, else the name (`small fw-semibold text-truncate`, two nodes by condition); `div.small.mt-1` with `span.pg-price-block.has-discount` (original, current) when `__related_original_price` is not empty, else `span.pg-price-block > span.pg-price`.
- Square pictures: `.pg-civ-cross-sell .card-img-top{aspect-ratio:1/1;object-fit:cover}` in `pg_carousel_layout.css` (v6) instead of the old inline style. `.pg-price-block` is inline-flex, so the renderer's line breaks add no gap.
- Same words, links, pictures and classes in order as `_civ_render_cross_sell()` with the view's settings (it was never given options), which is gone.

## What is __has_related?
- Set by the detail view after it reads the related rows: empty when `cross_sell_enabled` is off or no product was found (no box, as before), `1` with rows, `0` without (the empty box the old block left as the variant script's target). A list the design placed itself is never switched off.

## Where is it added?
- A loop tail with the `tree` flag (`pg_sw_default_loop_tail_parts()`, editor `SW_DEFAULT_LOOP_TAILS`): at the end of the main loop once, at the end of the tree without one, unless the tree places `__cross_sell_html` or a `product.related` list anywhere (a list of its own took the block's place before, too). A node bound to the token, or a text that is the token alone, becomes the host. The detail view's starter holds the part in its loop.

## How does a variant switch redraw it?
- `pg_civ_variants.js` (v7) posts `get_cross_sell_for_product` with `widget_id` (and `detail_page_id`, both in the variant payload); `_civ_cross_sell_part_html()` reads the widget's tree through the door, finds the part (`_civ_cross_sell_part_node()`, the first `pg-civ-cross-sell` node), reads the variant's related rows and draws the part alone (`_civ_cross_sell_part_draw()`); the script swaps it in. Nothing to suggest: '' and the cards shown stay. Tokens of the record other than `__id` read empty there.

## Tests?
- `tests/widget_default_parts_cross_sell_test.php`: old cards vs part by words, classes, links and pictures; none, off, the token; placement; the redraw keeps the design's changes; editor twins under Node.
