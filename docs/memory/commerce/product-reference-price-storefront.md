---
name: product-reference-price-storefront
description: Reference price on the storefront: __reference_price / __reference_label tokens, variant payload, legacy catalog print, one page read by pg_price_reference_page()
---

## Which tokens carry it?
- `__reference_price` (the amount with the visitor's currency, `pg_visitor_money()`, escaped like `__original_price_formatted`) and `__reference_label` (through `lang()`): `catalog_listing` cards and `catalog_item_view` (`includes/fn/widgets_catalog.php`). Both `''` when there is nothing to show, so a bound element collapses; the not-found map and group cards print them empty.
- Built by `pg_price_reference_tokens()`. Labels (`pg_price_reference_label()`): `lowest` "Lowest price of the last {var:1} days", `current` "Current price", `manual` "Regular price".
- The variant payload (`$vproducts`) carries `reference_price` / `reference_label`, so `pg_civ_variants.js` swaps them with the rest.
- Designer: "Price" group of `SW_CATALOG_TOKEN_GROUPS` and `SW_CATALOG_ITEM_TOKEN_GROUPS`; `__reference_price` is a currency sample, `__reference_label` a text sample.

## When does a product get one?
- `lowest_*`: only while the price shown is reduced by an offer (the rules ask for the price before an advertised discount); a regular price gets none.
- `manual`: the compare-at price when above the price shown, offer or not.

## How is a page read?
- `pg_price_reference_page($items, $now)`: `$items` is id => `price` shown (minor units, rounded) and `reduced`. At most two statements per page, never per product: `pg_price_history_page_since_sql()` (the oldest moment a current price began, among the reduced products) and one `pg_price_history_window_sql()` from that moment minus the rule's days to now. Rows are grouped by product; each product's end comes from `pg_price_history_reference_window_end()`, then `pg_price_history_reference()`. Under `manual` one `compare_at_price` read instead, behind `pg_compare_at_price_ready()`.
- `pg_price_history_reference_window_end()`: the newest row at or before now starts the current price; when the row before it is higher, the window ends at that row's `changed_at`, else now (product-reference-price-decisions.md). A row stamped after now is ignored.
- The listing reads once for the page; the product page once for the variant group (or the product alone).

## And the legacy catalog page?
- `get_catalog.php` calls `pg_price_reference_catalog_items($items, $discounted_product_prices)` once per item list (top/featured/new together, the list, the custom layout's items); product groups and donations get none.
- System layout: `pg_price_reference_html()` adds `<div class="reference_price">` (label and amount spans) after the product's `<div class="price">`; nothing else moves.
- Custom layout: `$item['reference_label']`, `reference_price` (plain text amount) and `reference_price_info` (the same HTML line). The starter `includes/templates/catalog.php` prints `reference_price_info`; layouts already saved print it only when edited.

## How was it verified?
- `tests/price_history_test.php` (window end, the reduction not its own reference, page-since query, labels); `check_bindings.php`. No database or browser run.

Related: docs/memory/commerce/product-reference-price.md, docs/memory/designer/token-dictionary.md
