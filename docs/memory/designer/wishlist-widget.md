---
name: wishlist-widget
description: Favourites system widget (kind wishlist, widgets_wishlist.php): whose list it shows, tokens, flags, the two row actions and their hidden forms, page cache, My Account link, shop templates
---

## What does it show, and to whom?
- Kind `wishlist` ("Favourites"), renderer `_render_system_widget_wishlist()` in `includes/fn/widgets_wishlist.php`, dispatched from `$account_renderers` (`designer.php`). The core is `commerce/wishlist.md`.
- The request's own list only: `pg_wishlist_merge()`, then one joined query on `pg_wishlist_owner_sql(pg_wishlist_owner(false))` and `pg_wishlist_product_live_sql()`, newest first, `max_results` (default 100, at most 500). Signed in: the account's list. A guest with the `pg_wishlist` cookie: their list (owner decision: guests open it before signing in). No cookie: the empty state. Never another person's list.
- Drawing is pure (`pg_wishlist_widget_html($tree, $sid, $rows, $ctx)`, tested in `tests/wishlist_widget_test.php`); the renderer only reads and hands over.

## Why is it never cached?
- `pg_page_cache_personal('wishlist')` is the renderer's first line, before every return: the router's file cache does not read the wishlist cookie, so a stored copy would be served to other guests.

## Which tokens and flags?
- Row (loop_area), the catalog cards' names: `__id`, `__name`, `__short_description`, `__detail_url` (`pg_sw_catalog_detail_page_name()`, `#` without one), `__image_url`, `__image_alt`, `__price`, `__price_formatted` (offer price from `get_discounted_product_prices()`, strike block), `__original_price_formatted` (raw: money is markup), `__discount_percent`, `__out_of_stock_label`; and `__added_date`.
- Static: `__wishlist_count`, `__login_url` (`login_page_id`, else the legacy sign-in, `?send_to=` this page), `__shop_url` (`shop_page_id`, else the first page with a catalog widget, else the legacy catalog page; the link drops when empty).
- Flags: `has_items`, `no_items`, `is_signed_in`, `is_signed_out`; row: `item_in_stock`, `item_out_of_stock`, `item_has_discount`. Static flags go on the static tree after the split, row flags per row.

## How do Remove and Add to Cart post?
- Bindings on a component btn or a `<button>`: `wishlist_remove`, `wishlist_add_to_cart` (`_sdBindingActionRows()` offers them on buttons only). `_pg_wishlist_wire_actions()` makes the button `type="submit"` with `form="pgWlRemove_pgw{sid}r{pid}"` / `pgWlCart_…`; a link bound to one, one outside the rows, and Add to Cart of a product out of stock (tracked, none left, no backorder) are dropped.
- The hidden forms are printed after the widget, never in a card, list or table row. Remove: `wishlist.php` `action=remove`, `product_id`, token, `send_to` this page. Add: `catalog_detail.php` with the catalog card's fields, `next_url` = this page `?cart_added=<id>` (the catalog notice, `pg_sw_cart_toast_script()`).
- Messages node `catalog_detail`: a refused add to cart comes back here with its error.

## Default layout?
- Only the JS starter (`case 'wishlist'` in `_buildStarterTree()`): heading, signed-out note, empty state with the shop link, card grid. Like every account kind there is no PHP default tree or server fallback; templates use `'tree' => 'starter'`.

## Where is it linked?
- My Account: `__my_account_wishlist_url` (`_pg_my_account_extras()`, shop sites, `pg_wishlist_widget_page_url()`: first page with the widget the visitor may open; no legacy page type). Starter link and `SW_MY_ACCOUNT_TOKEN_GROUPS`.
- Store, boutique, bookshop templates: page `favourites` (public folder, `requires` ecommerce), an account card or link, and a footer link `{{page:favourites}}`: the way a guest reaches their list, since the My Account link is empty for guests and that page asks them to sign in.

Related: docs/memory/commerce/wishlist.md, docs/memory/designer/system-widget-bindings.md
