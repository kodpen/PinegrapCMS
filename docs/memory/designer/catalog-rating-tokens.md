---
name: catalog-rating-tokens
description: Product rating in the catalog widgets: __rating_avg / __rating_count tokens, has_rating / no_rating flags per card and on the product page, columns before 11.0.14, variant payload and switch
---

## Which tokens carry a product's rating?
- `__rating_avg` (one decimal, the site's number format via `pg_format_number()`) and `__rating_count` (plain integer) in `catalog_listing` and `catalog_item_view` (PR #1560), from `products.rating_avg` / `rating_count`: published, starred reviews of verified purchases (`commerce/product-reviews-verified.md`).
- Both are `''` while the count is 0, so a bound element collapses (`[data-pg-bind]:empty`) and no rating shows without a verified review behind it. One helper fills them: `_pg_catalog_rating_values()` (`includes/fn/widgets_catalog.php`), also for the not-found map.
- Designer: group "Reviews" in `SW_CATALOG_TOKEN_GROUPS` and `SW_CATALOG_ITEM_TOKEN_GROUPS`; canvas samples `4.6` / `12` in `_SD_NUMERIC_TOKENS`.

## How does a wrapper (stars, "(12)") hide without a rating?
- "Show only when" `has_rating` / `no_rating` (`SW_VISIBILITY_FLAGS`, both catalog kinds). They read the same count as the tokens. `pg-hide-if-empty` does nothing in catalog widgets.
- Listing: the card is drawn before the products are read, once for each state (`_pg_catalog_card_templates()`: `_pg_sw_mark_visibility()` with only these two flags, then `_pg_sw_resolve_visibility()` on the card alone); a row takes the card of its state. Only the card (loop area) is marked; an element outside it, or another flag, is drawn as before. Group rows (categories, select groups) have no rating state: their card (`'group'`, resolved with no flag set) draws neither flag's elements. When nothing visible is left (`_pg_catalog_html_shows()`: text or media, not a token in an href or class nor the add-to-cart button), as with two alternative cards side by side or in one unflagged column or link, it falls back to the card without a rating, or category navigation would vanish.
- Product page: the tree is marked before the split and resolved once the product and its variants are read, on the card and the surround, before the variant picker looks for its anchors and before values go in. The state that does not fit the product shown is cut, unless a variant switch can change it (below).
- The no-products check counts both cards: a card drawn only with a rating is still a card.

## What does a variant switch do with the flags?
- When the variant script is on the page and the variants differ in rating (`_pg_catalog_rating_states_differ()`), both states stay (`_pg_catalog_rating_flags_keep()`): each element of a flagged span gets `data-pg-rating-flag="<flag>"` in its own opening tag, the state that is off also `hidden`; loose text gets a span (`_pg_catalog_rating_flag_mark()`). No wrapper goes around an element: `.row > .col`, flex and grid items and `<p>` keep working.
- A `[data-pg-rating-flag][hidden]{display:none!important}` rule printed before the form beats `d-flex` and a design's own display.
- `applyVariant()` in `pg_civ_variants.js` sets `hidden` from the variant's `rating_count`.
- Custom HTML whose tags do not close has no element end to mark: kept or cut for the variant shown first.

## What about columns, variants and structured data?
- The five SELECTs (listing, three product page queries, variant query) take `_pg_catalog_rating_columns(pg_reviews_ready())`: before the 11.0.14 columns exist they read `0 AS rating_avg`, because a missing column fails the whole query and the listing shows "No products found".
- `$vproducts` carries bare `rating_avg` / `rating_count`: `pg_civ_variants.js` swaps only fields it is given, and switches the flagged elements from `rating_count`.
- `_pg_build_product_jsonld()` and the RSS columns stay without a rating: designer aggregateRating, the review form and `__reviews` wait for designable-comments slice 20.
- Tests: `tests/catalog_rating_tokens_test.php`; `php tools/check_bindings.php` counts both tokens and both flags.
