---
name: marketplace-listing-view
description: Listings view of marketplace_products.php (#2066): article cards with pictures, category search by path words, one category for many articles, required attributes first
---

## What is it and why?
- `marketplace_products.php?account=N&mode=listings`, for a connector with `category_tree`. Each article (`mp_listing_articles()`, a product group with attributes) is a card: picture (the group's, else its first product's), code, title, product count, category (its own name first, the path above), state, the required answers it lacks, the last refusal.
- It replaced the account screen's "Listing articles" card (`#mp-list`): a text row per article, a `<datalist>` of up to 6,000 category paths, one article at a time, and the `categories`, `category`, `attributes`, `list_now` actions of `marketplace_settings.php`. The owner asked for a picture-led interface (2026-10-26). The account screen's "Matched products" card links here ("Listings").

## Where does an article stand? (`includes/api/outbound/listing_articles.php`)
- `mp_articles_state()`: `error` (map row's `last_error`), `none` (no category), `unread` (the category's attributes are not cached), `missing` (a required answer is empty), `ready`. Filters: all, no category, answers needed (`missing` + `unread`), ready, refused.
- "Required" is `mandatory` and not `is_variant`, the same test `mp_listing_items()` refuses by; a variant attribute is answered by the products (`marketplace-variant-attributes.md`).
- States are worked out from the cache only (`mp_articles_definitions()`); nothing is asked of the marketplace to draw the page.

## How is a category chosen?
- A search box: every typed word (split on spaces and `>`, at most six) must be in the leaf's path (`mp_articles_category_search()`, `LIKE` with `%` and `_` escaped), shortest paths first, 40 at most. So "oto ampul" finds "Otomobil > Aydınlatma > Ampul".
- Tick many cards and "Choose category" files them all (`mp_articles_set_category()`); groups without attributes are skipped. A changed category drops the old answers (`attributes_json` assigned before `remote_category_id`, `marketplace-listings.md`). The attributes are read once, right then; a failure is shown as a warning.
- One article whose category asks for something opens its answers next.

## How are attributes answered?
- A dialog drawn by the screen (`list_form`, which reads the attributes if they are not cached): required first, then "From the variants", then the optional ones folded in `<details>`. Save writes `attributes_json` and clears `last_error`.

## How is it listed?
- "List on the marketplace" (card or ticked, where the connector has `create_listings`): each article is checked with `mp_listing_items()` first and refused with its reason, else queued with `mp_listing_enqueue()`; the job lists it.

Related: docs/memory/commerce/marketplace-listings.md, docs/memory/commerce/marketplace-shop-view.md
