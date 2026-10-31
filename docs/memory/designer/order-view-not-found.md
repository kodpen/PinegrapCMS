---
name: order-view-not-found
description: Order view widget with no, unknown or foreign order id: message with a My Account link and a 404 with noindex; account order links skip order receipt e-mail pages
module: widgets
---

## What does the order view show and answer when there is no order to show?
- `_render_system_widget_order_view()` reaches its not-found state three ways: no or non-numeric id, no such order, an order the visitor may not see (not the signed-in owner, not the order this session just placed, not the receipt e-mail's order). All three look the same, so ids cannot be probed.
- The state calls `pg_sw_item_missing(true)`: the page answers 404 with `X-Robots-Tag: noindex` and goes into the 404 log, as the form item view and the catalog do. `pg_sw_item_missing()` itself does nothing in an e-mail render (a receipt with a stale order id) or an SEO pass (the designer canvas preview runs under `pg_seo_rendering(true)`); the widget adds no mode check of its own.
- A tree without `^^__not_found_message^^` shows the message through its message area with a My Account link after it (`pg_sw_order_not_found_message_html($message, $account_url)`: escaped once, no link when the address is `''`). The address comes from `_pg_acct_page_url(0, 'my account')`, built only in the not-found closure, so a found order runs no extra query. A tree that places the token keeps its own design and gets no link.

## Why?
- Issue #3457, PR #3545: the page answered 200 with an empty or bare body for every case (a soft 404), while blog post and conversation pages answered 404 with a message. Test: `tests/order_view_not_found_test.php`.

## Which page do the My Account order links open?
- `pg_sw_page_type_widget_url('view order')` (used by the order history links and `get_page_type_url('view order')`) takes the first page carrying an `order_view` widget, sorted by title. Every design template has the private "Order Receipt E-mail" page beside the public "Order Summary"; the e-mail page sorted first in both languages, so customers landed on a page they could not open.
- For `'view order'` the receipt e-mail pages are dropped first (`pg_sw_pages_without()`): `order_receipt_email_page_id` from `express_order_pages`, `order_preview_pages`, and the `express_order` / `order_preview` widgets' `system_region_config` (a fresh template site has not mirrored the value into the tables yet; the express order widget mirrors it only when it renders). Nothing left: `''`, and `_pg_acct_page_url()` falls back to the legacy view order page.
