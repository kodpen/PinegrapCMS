---
name: comments-widget-product-page
description: Product reviews on a designed product page (standard page with a catalog_item_view widget): thread, add_comment.php, links, e-mails, recent lists, canvas, templates, aggregateRating
module: comments
---

## Which page is a designed product page?
- A `standard` page whose top level places a catalog_item_view widget and no form_item_view widget (`pg_sw_page_widgets()`, shared refs of the page tree only). The store, boutique and bookshop templates' product page is one.
- `pg_comment_thread_page_type($page_id, $page_type)` answers `'catalog detail'` for it, any other page its own type; the pure rule is `pg_comment_product_view_page_type()` (`includes/fn/comments.php`). A record view decides first, as everywhere.

## What did it show before, and why change it?
- Before 2c1 such a page's comments were the page's own thread: no purchase check, no verified badge, no rating, every product sharing one list. Under 28/B only verified purchases may review a product, and the plan's `__reviews` list is the comments widget ([[comments-widget-item-threads]]).

## Who reads the type through it?
- `pg_comment_thread_verify()` (so `add_comment.php`), when the page shows no record: a post takes only the product or group the page could show, the review gate then applies by item type.
- `_render_system_widget_comments()`: `$thread_type`; the address item as on catalog detail, with fact `product_view`: no `/` in the address is the product `?pid=` names (the widget's own fallback), else no thread, never the page's own.
- `_pg_comments_widget_email()`: the e-mail's decision and links.
- The recent list's page facts: such a page is refused as a source (`classic`): its comments are item threads.
- Links: `get_query_string_for_page_url()` gives a product's or group's `/address` on any page type outside its cases (admin and watcher mails, `add_comment.php`'s redirect).

## What do the canvas, the templates and the markup add? (2c2)
- Canvas: `_sdCommentsProductViewPage(p)` (same rule, `_swTabHasWidget()`) makes `_sdCommentsPageThread()` answer `product`; Preview says "On this product page it shows the reviews…".
- The store, boutique and bookshop product page: comments on with stars, sign-in, auto-publish, staff mail; a "Reviews" section (col-lg-8, centred) with widget `product_reviews` (kind `comments`, newest, featured `none`, flat, 10 a page, starter tree, shop only).
- `aggregateRating` in `_pg_build_product_jsonld()` only when `pg_comment_product_view_rating_shown()` (pure `pg_comment_rating_markup_decision()`): comments and stars on, a comments widget on the page, the address thread is this product's (a select group's thread counts in no rating).

## What is left as it was?
- The classic comments block there now reads the product's thread too, and its Reviews frame is left out when it draws nothing ([[comments-block-product-page]]).
- `aggregateRating` stays widget-only.
- The address is read as `get_catalog_item_from_url()` reads it (groups first): a browsing group's address shows its group thread under the widget's "not found".

## Where is it tested?
- `tests/comments_product_view_test.php`: the type table, the address item with `product_view`, the readers, the rating decision, the templates; the canvas in `comments_widget_designer_test.php` (item threads).
