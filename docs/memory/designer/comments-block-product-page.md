---
name: comments-block-product-page
description: The classic comments block on a designed product page (#3546): the product's reviews with the review gate, and its Reviews tab or section left out when it draws nothing; canvas twin
module: widgets_catalog
---

## Which thread does the block read there?
- `get_page_content.php` reads the type through `pg_comment_thread_page_type()` as the widget does: on a designed product page ([[comments-widget-product-page]]) the block picks the product with `pg_comment_address_item()` (`product_view`: path product or `?pid=`) and `pg_comment_thread_verify()`; no product it could show: it draws nothing.
- The review gate then applies by item type (sign-in link, `pg_review_refusal_message()`, badge, stars). An empty thread prints "No reviews yet." before the form or refusal (Bootstrap path only).
- Classic pages get the same type as before: their HTML is unchanged byte for byte.
- Page-thread comments stored on such a page earlier no longer show; they stay in Comment Moderation.

## When is its frame left out, and which frame?
- `_render_system_widget_catalog_item_view()` asks `_pg_civ_comments_block_will_draw($page_id)` (pure `pg_civ_comments_block_draws()`): comments on (the Preview's unsaved settings too) and no thread comments widget on the page (a recent list does not count). No page id (e-mail, unsaved Preview): the tree stays.
- When it will not draw, `_pg_civ_drop_comments_frame()` runs before the visibility marks and the split: a) the tab pane around the block, its trigger (`data-bs-target` or `href` `#<pane id>`) and the trigger's li / nav-item; the first tab and pane left open when the removed one was open; b) else the parent holding only headings beside it (the gallery starters' "Reviews" section); c) else the region.
- Template sites: the bottom `product_reviews` widget shows the reviews, so the tab goes and the list is not shown twice. Templates unchanged.
- Approximation: a comments widget placed only inside a shared component is not seen (`pg_sw_page_widgets()` reads the page tree).

## What does the canvas show?
- `_sdCivCommentsFrameOff()` (catalog_item_view only) fades the same nodes (`_sdCivCommentsFrameNodes()`): "comments are off" or "the Comments widget on this page shows the reviews".
- The block's placeholder says "The product's reviews will appear here" on a designed product page.

## Where is it tested?
- `tests/catalog_item_view_reviews_tab_test.php`: the decision, the frame drop on the tab, section and bare shapes, the active move, the wiring, the canvas twin under Node.
