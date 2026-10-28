---
name: comments-widget-product-page
description: Product reviews on a designed product page (standard page with a catalog_item_view widget, 2c1, PR #2817): read as catalog detail for the thread, add_comment.php, links, e-mails and recent lists
module: comments
---

## Which page is a designed product page?
- A `standard` page whose top level places a catalog_item_view widget and no form_item_view widget (`pg_sw_page_widgets()`, shared refs of the page tree only). The store, boutique and bookshop templates' product page is one.
- `pg_comment_thread_page_type($page_id, $page_type)` answers `'catalog detail'` for it, any other page its own type; the pure rule is `pg_comment_product_view_page_type()` (`includes/fn/comments.php`). A record view decides first, as everywhere.

## What did it show before, and why change it?
- Before 2c1 such a page's comments were the page's own thread: no purchase check, no verified badge, no rating, every product sharing one list. Under 28/B only verified purchases may review a product, and the plan's `__reviews` list is the comments widget ([[comments-widget-item-threads]]).
- Comments already stored there under the page's own thread no longer show on it; they stay in Comment Moderation.

## Who reads the type through it?
- `pg_comment_thread_verify()` (so `add_comment.php`), when the page shows no record: a post takes only the product or group the page could show, the review gate then applies by item type.
- `_render_system_widget_comments()`: `$thread_type`; the address item as on catalog detail, with fact `product_view`: no `/` in the address is the product `?pid=` names (the widget's own fallback), else no thread, never the page's own.
- `_pg_comments_widget_email()`: the e-mail's decision and links.
- The recent list's page facts: such a page is refused as a source (`classic`): its comments are item threads.
- Links: `get_query_string_for_page_url()` gives a product's or group's `/address` on any page type outside its cases (admin and watcher mails, `add_comment.php`'s redirect).

## What is left as it was?
- The classic `comments_block` region on a designed page still reads the page's own thread.
- The address is read as `get_catalog_item_from_url()` reads it (groups first, any display type); the catalog_item_view widget shows only a select group, so a browsing group's address shows its group thread under "not found".
- Canvas, templates' review section and `aggregateRating` there: 2c2 (`plans/storefront-conversion-pack.md`).

## Where is it tested?
- `tests/comments_product_view_test.php`: the type table, the address item with `product_view`, each reader taking the type through the function.
