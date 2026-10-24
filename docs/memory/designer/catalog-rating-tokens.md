---
name: catalog-rating-tokens
description: Product rating in the catalog widgets: __rating_avg / __rating_count tokens, has_rating / no_rating flags per card and on the product page, columns read before 11.0.14, variant payload
---

## Which tokens carry a product's rating?
- `__rating_avg` (one decimal, the site's number format via `pg_format_number()`) and `__rating_count` (plain integer) in `catalog_listing` and `catalog_item_view` (PR #TBD), from `products.rating_avg` / `rating_count`: published, starred reviews of verified purchases (`commerce/product-reviews-verified.md`).
- Both are `''` while the count is 0, so a bound element collapses (`[data-pg-bind]:empty`) and no rating shows without a verified review behind it. One helper fills them: `_pg_catalog_rating_values()` (`includes/fn/widgets_catalog.php`), also for the not-found map.
- Designer: group "Reviews" in `SW_CATALOG_TOKEN_GROUPS` and `SW_CATALOG_ITEM_TOKEN_GROUPS`; canvas samples `4.6` / `12` in `_SD_NUMERIC_TOKENS`.

## How does a wrapper (stars, "(12)") hide without a rating?
- "Show only when" `has_rating` / `no_rating` (`SW_VISIBILITY_FLAGS`, both catalog kinds). They read the same count as the tokens. `pg-hide-if-empty` does nothing in catalog widgets.
- Listing: the card is drawn before the products are read, once for each state (`_pg_catalog_card_templates()`: `_pg_sw_mark_visibility()` with only these two flags, then `_pg_sw_resolve_visibility()` on the card alone); a row takes the card of its state. Only the card (loop area) is marked: an element outside it, or another widget's flag, is drawn as before. Group rows (categories, select groups) have no rating state: their card (`'group'`, resolved with no flag set) draws neither has_rating nor no_rating elements, and the child-group block needs that card to be non-empty.
- Product page: the tree is marked before the split and resolved once the product is read, on the card and the surround, before the variant picker looks for its anchors and before values go in.
- The no-products check counts both cards: a card drawn only with a rating is still a card.

## What about columns, variants and structured data?
- The five SELECTs (listing, three product page queries, variant query) take `_pg_catalog_rating_columns(pg_reviews_ready())`: before the 11.0.14 columns exist they read `0 AS rating_avg`, because a missing column fails the whole query and the listing shows "No products found".
- `$vproducts` carries bare `rating_avg` / `rating_count`: `pg_civ_variants.js` swaps only fields it is given. A variant switch moves the values, not the flags (decided on the server for the variant shown first).
- `_pg_build_product_jsonld()` and the RSS columns stay without a rating: designer aggregateRating, the review form and `__reviews` wait for designable-comments slice 20.
- Tests: `tests/catalog_rating_tokens_test.php`; `php tools/check_bindings.php` counts both tokens and both flags.
