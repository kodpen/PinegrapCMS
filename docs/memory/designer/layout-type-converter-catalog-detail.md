---
name: layout-type-converter-catalog-detail
description: A catalog detail page's layout file into the Catalog Detail widget (LE8c): each piece, the record and cross_sell options, the widget's new flags, tokens, sections and sidebar forms
module: layout_convert
---

## What does the catalog detail's map do?
- `_pg_lc_catalog_detail_map()` (`includes/fn/layout_convert.php`) via `pg_layout_piece_map('catalog detail')` ([[layout-type-converter]]). The widget (`catalog_item_view`) shows one product: option `record` adds no list, the tree is the product.
- `$form_attributes` and `$currency_attributes` `form:strip` (the widget wraps its tree in its own form); `$footer_system`, `$currency_system` nothing.

## What does each piece become?
- `h($image_url)` `__image_url`, `$code` `__code`, `h($short_description)` `__short_description`, `$full_description` `__description`, `$price_info` `__price_formatted`, `$details` `__details`, `h($add_button_label)` `__add_button_label`, `h($back_button_url)` `__back_url`, `h($back_button_label)` `__back_label`.
- `foreach ($attributes …)`: loop `token:__variant_picker`, an element bound to the picker in its place (body not read).
- `$available_products` `has_products` / else `no_products`; `$product_row` `no_variant_choice`; `$product_pick_list` `has_product_choice` / else `no_product_choice`; `count($products) == 1` `one_product` / else `many_products`; `$recipient` `has_recipient_choice`; `$back_button_url` `has_back_button`; `$keywords` `has_keywords`; `$currency` `has_currency_choice`; `$quantity` kept.
- `$products[0]['description']`, `$product['description']` `__product_line`. Loops: `$products` `section:product_lines`, `$product_groups` `section:group_tree`, `$keywords` `section:keywords`.
- Controls: `product_id` bound `product_choice`, `ship_to` `recipient_select`, `quantity` `quantity`, the search box `search_query`; `add_name` stays plain, always shown as on the classic page; `currency_id` gets the widget's currency picker.
- The search form (`$search_attributes`) is bound to `search_form`. The cross-sell script's pieces are the widget's (else value `widget`, `json_encode($page_id)` nothing; the HTML converter leaves the script out), and the element with id `cross-sell` (option `cross_sell`) becomes `^^__cross_sell_html^^`: the widget's cross-sell part in its place ([[widget-default-parts-cross-sell]]).

## What did the widget gain?
- `pg_civ_layout_flags()` (pure) and `has_keywords`, `has_currency_choice`, `no_currency_choice`, marked before the split. Orderable: a select group's product not out of stock, else the product (back order counts).
- Tokens: `__code` (products.code), `__product_line` (`pg_civ_product_line()`), `__product_lines_html`, `__product_choice_options`, `__keywords_html` (`pg_civ_keywords_html()`, searches on the catalog page), `__group_tree_inner_html`, `__add_button_label` / `__back_label` (cfg, seeded from the type row, else "Continue" / "Back"), `__back_url` (the session's `previous_url_id`).
- The catalog page (`_civ_catalog_page()`): cfg `catalog_listing_page_id` / `_name`, else the classic `catalog_pages` row naming this page; a designed listing reads `query`, a classic page `<id>_query`. Without one the search form is left out.
- A form cannot hold another: the bound search form becomes `<div role="search">`, its controls name (`form=`) a form printed after the widget's, as the currency form is.
- Editor: flags, "Classic product page" tokens, section, form and value options, canvas samples (`_sdCatalogDetailSectionSample()`).

## What did the starting sites give?
- 6 file pages, 6 texts (986, 998, 1002 on each site): all convert with nothing dropped and no setting held, so each converts in the move run. The stock template converts whole too.

## Tests?
- `tests/layout_convert_test.php` (every text, the template), `tests/catalog_detail_layout_test.php` (flags, lines, keywords, the converted row and sidebar through the binding pass).
