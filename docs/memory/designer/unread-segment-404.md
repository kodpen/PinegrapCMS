---
name: unread-segment-404
description: A path segment after the page name that no widget reads (/about/xyz) answers 404 into the 404 log; which widgets mark it read and where the check runs (#4826)
module: widgets
---

## What does /<page>/<segment> answer when nothing reads the segment?
- 404 with `X-Robots-Tag: noindex`, no canonical, and a row in Pages › 404 log: `pg_sw_item_missing(true)`, the rule paged lists, product detail and the order view already follow (#2180, #3457, `order-view-not-found.md`).
- `get_page.php` cuts the segment off every `system`-layout page but a form list view; the page used to answer 200 with its canonical (soft 404). Other layouts never matched a page by the full name and already answered 404.

## Who reads the segment?
- A widget that reads it calls `pg_sw_segment_read(true)`: `catalog_item_view` (a product address), `catalog_listing` (a group in the page's path, not the home page's `?cat=`), `form_item_view` when the form has pretty URLs on (the router turned the name into `?r=`).
- `form_item_view` treats a segment as an asked record with pretty URLs off too: no record, a 404 of its own.
- The classic `catalog` and `catalog detail` page types read it themselves (`get_catalog_item_from_url()`); `pg_sw_unread_segment_missing()` leaves them out.
- The record-address router (`pg_sw_route_record_address()`) redirects before any of this.

## Where does the check run?
- `get_page_content()`: the flag is cleared beside `pg_sw_item_missing(false)` before the widget pass and asked after it, then the value before is put back (a page drawn inside another keeps the outer answer).
- Only for the page the request asked for (`_pg_sw_current_page_id()`), not under `PRETTY_URL_PATH` (a classic form list address draws the item view page), never in an e-mail or edit mode; `pg_sw_item_missing()` itself skips the SEO pass and lets a short link with `*` redirect first.
- Running sites: such a link now shows in the 404 log, where the owner adds a redirect. Test: `tests/unread_segment_404_test.php`.
