---
name: layout-type-converter-catalog
description: A catalog page's layout file into the Catalog widget's design elements (LE8b): each piece, the map options it brought, the listing's new flags and tokens, the run's settings order, the starting sites
module: layout_convert
---

## What does the catalog's map do?
- `_pg_lc_catalog_map()` in `includes/fn/layout_convert.php`, read through `pg_layout_piece_map('catalog')` ([[layout-type-converter]]). The items loop is the listing's list with the layout's own row; groups and products are one list on both sides.
- The categories loop (`$product_groups`) is the widget's group tree: loop value `section:group_tree`; the `<ul>` the loop filled becomes the node bound to the section, so the theme's `<nav>` and heading stay, the list inside is the widget's ([[catalog-listing-open-blocks]]).
- The search form (`form:search_form` on `$search_attributes`) is bound to `search_form`; `page_query` is bound to `search_query`, `page_submit` loses its name, `page_clear` becomes a second, empty `query` (the address reads the last one). The currency form's tags go (`form:strip`); its `currency_id` select stays.

## What does each piece become?
- Row: `h($item['url'])` `__detail_url`, `image_url` `__image_url`, `short_description` `__short_description`, `price_info` `__price_formatted`. `$item['url']` around the link (two blocks): kept. Image, short description, price: row flags `catalog_item_has_image`, `_has_short_description`, `_has_price`.
- `$items` `has_items` (it may cover the list, `flag_list`), else `no_items`; `$mode == 'search'` and `$query != ''` `has_query`, `$mode == 'browse'` `no_query`; `$currency` / `!$currency` `has_currency_choice` / `no_currency_choice`.
- `$full_description` `__active_cat_description`, `$code` `__active_cat_code` (both raw, empty in a search), `h($query)` `__search_query`, `number_format($number_of_items)` `__result_count`; `$page_id` the word `page`; edit grids nothing.
- "product<s>" (`$number_of_items > 1` inside a line of text): the line drawn twice, under `many_results` with the "s" and under `one_result` without (map option `opposite`). A heading holding such a line becomes a semantic element (a `content` heading draws no children).

## What did the widget gain?
- `pg_catalog_listing_flags()`: the eight surround flags, resolved on the static part with the paging flags. With no item, `has_items` around the list's place cuts it with the body: the layout's own words stand for the widget's empty message.
- `pg_catalog_row_flags()`; a card with an element under one (`row_flagged`) is resolved per row's flags in `_pg_catalog_card_for()`, a group's card too.
- Tokens `__active_cat_description`, `__active_cat_code` (in `pg_sw_raw_token_keys()`), `__search_query`, `__result_count` (groups and products over all pages). A `currency_id` control in the tree gets `pg_sw_currency_picker()` and the form after the widget ([[checkout-currency-picker]]). The editor's flag list, token palette and canvas sample carry them.

## Which settings win in the run?
- What the layout sets, then `pg_layout_widget_seed()`'s, in `pg_ltd_type_file_widget()` as in the editor's Convert (`cfg_bound`: a bound `search_form` sets `search_enabled`). A custom catalog layout's search form works whatever the page's Search setting; every starting-site catalog page has it off.

## What did the starting sites give?
- 10 file pages, 9 texts (985, 997, 999, 1000, 1001 on each site; tr 1000 and 1001 the same): all convert with nothing dropped, no setting held, so each converts in the move run. `tests/layout_convert_test.php` runs every text; `tests/catalog_listing_layout_test.php` draws a converted card and surround.
- Catalog detail: [[layout-type-converter-catalog-detail]].
