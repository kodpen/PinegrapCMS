---
name: wishlist-heart
description: Favourite heart in the catalog widgets: catalog_wishlist / wishlist actions, the binding pass, pg_wishlist.js and its injection, the variant event, why pages stay cacheable
---

## How is a heart placed?
- A button (component btn or semantic `<button>`; a link is not offered) bound to `catalog_wishlist` in a catalog_listing card or `wishlist` in catalog_item_view (`_sdBindingActionRows()`).
- Starters carry one (`_sdWishlistHeart()` in `_buildStarterTree`), and so do the product cards of the store, boutique and bookshop templates (`$heart`): `bi-heart` icon plus visually hidden "Add to favourites". Catalog widgets have no PHP default tree or server fallback; the templates' shop and product pages use the JS starter. The heart raised store to 1.0.13, boutique and bookshop to 1.0.12, hello-pinegrap to 2.4.14.

## What does the server draw?
- `_pg_catalog_wishlist_heart()` (`widgets_catalog.php`), from both binding passes: `<button type="button">` (on the product page it sits in the add-to-cart form), `data-pg-wishlist="^^__id^^"`, `aria-pressed="false"`, `aria-label` only for a button without words (`_pg_catalog_node_has_words()`), `data-pg-wishlist-hidden="1"`; its classes stay the designer's.
- Why a mark, not `d-none`? Bootstrap's `d-md-*` (same weight, later) beats `d-none` from md up, so a `d-none d-md-inline-block` heart showed dead without JS or state. `pg_wishlist_inject()` hides the mark with `[data-pg-wishlist][data-pg-wishlist-hidden]{display:none!important}` (0,2,0), before the first `</head>` if it precedes the first heart, else with the script.
- No token, no form: the page is the same for everyone and stays cacheable (a `get_token_field()` would make it uncacheable).
- Without the table (`pg_wishlist_ready()`, context `wishlist_ready`) the node is dropped. No site switch (owner), so no `_pg_catalog_binding_disabled()` twin; the canvas always draws it.
- The editor's Preview and canvas ghost cards (`pg_sw_preview_overrides()`, context `wishlist_preview`) get no mark: no script runs there.
- A product group's card loses it (`_pg_catalog_wishlist_strip()`): its `__id` is the group.

## What does pg_wishlist.js do?
- Added by `pg_wishlist_inject()` (`includes/fn/wishlist.php`) from the `get_page_content.php` chain when the HTML holds `data-pg-wishlist="`, with `window.pgWishlist = {url, labels}` (`lang()`); `?v=filemtime`, no min twin, like `pg_ai_summary.js`. ES5 + fetch, no jQuery.
- One GET `?action=state` (no-store, `pg_lang` from `<html data-pg-lang>`): each heart with a product gets `aria-pressed`, `active`, `bi-heart` / `bi-heart-fill`, and loses the mark; classes are never touched. A heart left without a product gets the mark again. No answer: all stay hidden. A `pageshow` with `persisted` (back/forward cache, `env.window`) reads the state again: list and token may have changed.
- Click (delegated): POST `add` or `remove` (not `toggle`: a change made in another tab cannot flip it the wrong way) with the state's token and `X-Requested-With`. Every heart of the product changes at once; a refusal puts them back and its message (else `labels.failed`; a fetch that rejects gives no words, never the browser's English text) goes to a polite status line; a 403 reads the state again for a new token. A second click while the product's write runs is ignored (`aria-busy`).
- Variant groups: `pg_civ_variants.js` dispatches `pg-civ-product-change` (`detail.productId`) on the form in `applyVariant()`; the hearts in that form take the new id and its busy state (`pending`), so the end of the old product's write leaves no stale `aria-busy`. Its stamp `$_civ_v_v` is '5'.

## Tests?
- `tests/wishlist_heart_test.php`: both passes on fake trees, the strip, the inject, pins on the offer, starters and the `applyVariant()` call, a heart in every template card loop, and the script under Node on a fake document (`module.exports = {create}`).
