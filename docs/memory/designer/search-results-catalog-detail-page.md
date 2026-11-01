---
name: search-results-catalog-detail-page
description: Search Results widget: a product is a result only when a page opens it (chosen, else a Catalog Detail page the visitor can open); the editor names a site without one (#2062, #4453)
module: widgets
---

## Which page does a product result open?
`_pg_sw_search_collect()` (`includes/fn/widgets.php`) takes the widget's `catalog_detail_page_id`, else `pg_sw_auto_catalog_detail_page()`: the first page carrying a `catalog_item_view` widget (`pg_sw_widget_pages()`, title order), else the first `page_type = 'catalog detail'` page, that the visitor can open. The link is `/<detail page>/<item address>?previous_url_id=…`; `full_url` drops the query. `pg_sw_catalog_detail_page_name()` (lists, cart, checkout, feeds, sitemap) uses the same picker.

## Which automatic page counts as one the visitor can open?
- With a visitor: `check_view_access($folder, true)` on the page's folder, so a guest or registration folder counts (the visitor can get in), a private folder only for its members and for Manager and above.
- Without one (`USER_LOGGED_IN` undefined: a scheduled feed or e-mail): the folder's access type must be public, guest or registration.
- Why: on dev both Catalog Detail pages sat in the private Drafts folder; every product result of the Help Center search linked to one and sent the visitor to `/giris` (#4453). No page the visitor can open means no catalog results, as below.
- A configured `catalog_detail_page_id` is not checked: the owner chose it. The current design is not preferred (no current-page context reaches the collector).

## What happens on a site with none?
The catalog is not searched: the detail page is looked up first and `get_catalog_search_results()` runs only behind `$want_catalog && $detail_name !== ''`. Pages are searched as before. With scope `products` the search then finds nothing and prints the no-results heading.

## What did it fix?
A site with products but no product detail page listed the item with `href="#"` and counted it ("1 result found"): the row was built with `url = ''` and the renderer's `'#'` fallback drew it as a link (#2062, dev.pinegrap.com `/arama?query=kalem`). The fallback in `_render_system_widget_search_results()` stays as a guard; no row reaches it now.

## Why leave the item out instead of listing it without a link?
A result the visitor cannot open is noise in the count and the heading, and the legacy Search Results page shows a product only through its detail page too. The owner's expectation on the issue allowed either; the smaller behaviour was chosen.

## What does the editor say?
Under the search widget's "Product detail page" setting (`style_designer.js`, search_results settings): when no page is chosen, `list_catalog_detail_pages` returned none and no tab of the design carries a `catalog_item_view` widget (`_swTabHasWidget()`), an amber line says products stay out of the results until the site has one. The server list and the PHP lookup cover the same pages (legacy catalog detail pages and designed pages with the widget).

## How is it tested?
`tests/catalog_detail_page_pick_test.php`: the automatic page skips folders the visitor cannot open. `tests/search_results_catalog_link_test.php`: the collector's order (lookup, guard, search) and that no catalog row is built without an address; the editor's hint inside the search settings. The collector needs a database, so it is read from its source, as the paging tests do.
