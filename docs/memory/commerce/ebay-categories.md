---
name: ebay-categories
description: eBay categories and item specifics on the Listings view: Taxonomy API with an application token, the cache key a site (ebay:EBAY_DE), aspects as attribute rows, free text with suggested values
module: ebay
---

## Where do an eBay account's categories come from?
- The Taxonomy API, with an application token of the same keyset (`pg_ebay_app_token()`, client credentials, scope `api_scope`), not the seller's token: its three sell scopes do not reach it.
- `mp_ebay_category_tree()` (`connectors/ebay.php`) asks `get_default_category_tree_id?marketplace_id=<site>`, then `category_tree/{id}` (gzip, kept up to 128 MB, timeout 120 s); `pg_ebay_category_rows()` flattens it without eBay's root node. An empty tree is an error, never an empty cache.
- `mp_ebay_category_attributes()` asks `category_tree/{id}/get_item_aspects_for_category?category_id=N` (two calls: the tree id is not kept). A category id that is not a number is never sent.

## Under which key are they cached?
- `mp_category_key($account)` (`connectors/base.php`): the connector's `category_key` entry, else the provider. eBay's is `pg_ebay_category_key()`: `ebay:EBAY_DE`, `ebay_sandbox:EBAY_DE` (one tree a site and environment; at most 27 characters of the column's 30). No table or column was added.
- Every read and write of `marketplace_categories` and `marketplace_category_attributes` goes through it: `mp_categories_refresh()`, `mp_category_attributes()`, `mp_listing_articles()` (its `$category_key`), `mp_articles_list()`, `mp_articles_category_search()`, `mp_articles_set_category()` and the Listings view's category count. n11 and Trendyol keep their provider as the key.
- An account whose site is changed keeps its pairings (`marketplace_category_map`), whose category ids are of the old site's tree: they show no path and are chosen again.

## How does an aspect become an attribute row?
- `pg_ebay_aspect_rows()`: the aspect's name is `attribute_id` and each value is its own id (eBay's item specifics have no ids; a listing sends them by name). A name over 64 characters is left out, not cut.
- `mandatory` = `aspectRequired`; `custom_allowed` = not `SELECTION_ONLY`; `is_variant` = `aspectEnabledForVariations`, so such an aspect sits under "From the variants" and is answered by each product's own option, the article's answer as fallback (`marketplace-variant-attributes.md`).

## How is a free-text aspect with suggested values answered?
- On the attribute dialog of Marketplace Products › Listings, an attribute with values and `custom_allowed` is a text box offering the values (`<datalist>`), not a closed select, for every marketplace: eBay's Brand takes the shop's own brand. A stored value id is shown as its text; the listing matches a text back to the id (`mp_listing_value()`).
- A field id made from a name that is not `[A-Za-z0-9_-]` is its md5's first 12 characters.

## What is not verified?
- Built from eBay's documentation; fixtures `tests/fixtures/ebay/default-category-tree-id.json`, `category-tree.json`, `item-aspects.json`; tests `tests/ebay_categories_test.php`. The first try against eBay's sandbox is the owner's.
