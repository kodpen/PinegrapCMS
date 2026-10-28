---
name: comments-widget-email-items
description: Comment e-mails about a product, group or event drawn by the comments widget (20c): which page shows which item, verified reviews only, links on the item's address
module: widgets_comments
---

## Which item thread does a comment e-mail draw?
- `pg_comments_email_decision()` takes an item thread when `pg_comments_email_item_page_ok($item_type, $page_type)` holds: a product on a catalog detail page, a product group on a catalog or catalog detail page, an event on a calendar event view page, the pages whose address shows that item ([[comments-widget-item-threads]]). Any other pair keeps the fixed markup.
- In a product's reviews (`pg_review_gate_applies()`, the branch passes it as `$review`) only a verified purchase (`verified_order_id > 0`) is drawn: the page shows no other comment there, so a link to a staff or pre-11.0.14 comment would lead nowhere. A quoted parent there is drawn only when verified too.

## Which links and values does it give?
- `get_query_string_for_page_url()` of the thread page's type: `/<address name>` for a product or group, `?id=<event>`; the View or Reply link of the fixed markup was already built so. An item with no address name (gone) draws nothing and keeps the fixed markup.
- `is_item_thread`, `is_review_thread` and `__comments_item_name` (`pg_comment_item_name()`), as on the page; `pg_comments_email_thread_values($settings, $item_name)`. A review is never featured (`featured` none, as `pg_comments_rows()` reads it).
- No reply link under a product: an answer there is a review, which only a buyer with a purchase left to review may write (`pg_review_eligibility()`). A group's or event's comment gets one under the usual rules ([[comments-widget-email]]).

## What does the editor show?
- The E-mail canvas state keeps the page's thread (`_sdCommentsStateThread()`): on a catalog detail page a review e-mail. `_sdCommentsPageThreadText()` says e-mails link to the item's, group's or event's address.

## What else did slice 20c check?
- `aggregateRating` on designer product pages needed no code: [[product-reviews-verified]] (commerce).

## Where is it tested?
- `tests/comments_widget_email_test.php`: the item decision table and page pairs, draw data of an event and a review, the branch wiring; `comments_widget_designer_test.php`: the E-mail state of an item thread. No sandbox run: the mail paths are unchanged and the branch reads only what the unit tests cover.
