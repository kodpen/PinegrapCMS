---
name: site-search-skipped-pages
description: Visual-editor pages site search never lists as items (bare product, record, error, checkout and order pages): pg_search_index_skipped_widget_types() (#9053)
module: site_search
---

## Which designed pages are not search items?
- A `standard` page carrying a widget of `pg_search_index_skipped_widget_types($index_types)` (`includes/fn/site_search.php`) is skipped in the page loop of `update_search_index.php`, whatever else it holds; its lists are still collected for the record items.
- The list is `pg_sitemap_unlisted_widget_types(pg_sw_page_type_widgets(), $index_types)` with the legacy types the index draws (`pg_render_pass_scope('index')['types']`): `form_item_view`, `catalog_item_view`, `error_page`, and the widget of each page type the index leaves out (email preferences, set password, view order, calendar event view, the checkout steps; the store's and calendar's widgets while that feature is off).

## Why?
- Drawn bare, such a page is its not-found or empty state. Symptom before (#9053): searching "seçenekler" found one result, the product page `/urun-detay` drawn without a product: an empty heading and "Ürün bulunamadı". The products are items under their own addresses (`/urun-detay/<product>`), the records likewise.
- It is the legacy rule for designed pages: `catalog detail`, `form item view`, `calendar event view` and the other left-out types are not in the index scope. sitemap.xml and llms.txt leave the same pages out.

## What stays an item?
- Pages of the types the index draws: sign-in, sign-up, logout, my account, search results, cart and express order (store on), folder view, calendar view (calendars on).
- An item already in the index for a skipped page leaves with the next full pass's sweep.

Tests: `test_designer_search_index_skipped_widget_types` in `tests/designer_search_index_test.php`.

Related: docs/memory/designer/designer-records-site-search.md
