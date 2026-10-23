---
name: product-review-request-email
description: The e-mail asking a buyer for a review (order_review_request; 2026.11.0, PR #TBD): what starts it, once per order, items, mail-merge fields, delete; the verified filter in moderation
---

## What starts it?
- `update_order()` (`update_order.php`), right after it starts the `order_shipped` campaign: `pg_review_request_start()` (`includes/fn/reviews.php`) runs the enabled profiles of `order_review_request` through `create_auto_email_campaigns()`. A profile has no item; its delay is the usual "after" schedule.
- So the first shipment that mails the customer starts it, and it asks about every bought line of the order, shipped or not; later shipments do not ask again. An order that never ships (downloads, services) gets none.

## When is nothing started?
- A save that keeps the shipping mail back: API `POST /orders/{id}/shipment` without `notify=true` (the default, so a backfill mails no one), the Workspace's tracking edit. `shipworks.php` and the marketplace import do not go through `update_order()`.
- No review columns (`pg_reviews_ready()`), an order without an account (`orders.user_id = 0`: the form signs the reader in with it), or not bought by the form's rule (`pg_review_bought_sql()`).
- The order has an `email_campaigns` row of the action in any status (sent, waiting, cancelled). Checked under `GET_LOCK(pg_review_request_lock_name(), 5)`, a name with the database's md5 and the order id (not held: nothing), so two saves at once start one. A shipment while no profile is enabled creates no row, so a later one may still start it.
- No product page (`pg_sw_catalog_detail_page_name(0)`) or no item with an address.

## Which items does it ask about?
- `pg_review_request_targets()`: lines with a product, saved-for-later left out (`pg_order_item_active_sql()`), in line order. A product in an enabled `display_type = 'select'` group is reviewed on the group (lowest id), as its page takes reviews; else the product, if enabled. One entry per item; none without an address. Name: `short_description`, else `name` (a product's SKU).

## Which mail-merge fields are there?
- `^^order_number^^`; `^^review_url^^`: first item's form, `<site>/<product page>/<address>#software_add_comment`; `^^review_links^^`: one `Name: address` line per item. The body page also gets `order_id` and `review_targets`.
- Filled in at creation; in HTML format escaped, line breaks as `<br>`. For a button link to `^^review_url^^` with the Link dialog or `<a href="^^review_url^^">`: `pg_mail_merge_address_hrefs()` (`includes/fn/mail.php`) takes off the `http://` the dialog adds, and an `href` starting with `^^` gets no site address or tracking code.

## Where does the link land?
- The page products open on. A legacy catalog detail page has the form at `#software_add_comment`; a visual-designer product page has none until plan step 2c (`plans/storefront-conversion-pack.md`).

## What cancels it?
- `delete_order()` cancels the order's `ready`/`paused` `order_abandoned` and `order_review_request` campaigns.
- `process_order_cancellation()`, in the request whose write cancelled the order (staff may cancel after shipping), calls `pg_review_request_cancel()`. A restored order gets no new request: the cancelled row ends it.

## How does Comment Moderation filter reviews?
- `view_comments.php`: `pg_filter_select('verified')` beside the sort menu: All comments / Verified purchases (`verified_order_id > 0`) / Not verified (`= 0`), `?verified=` '' / yes / no, kept in `$_SESSION['software']['comments']['verified']` (`pg_review_verified_filter_sql()`). Only with the review columns.

## Where are the tests?
- `tests/reviews_test.php` (address, fields, items, filter, pins for trigger, lock, once-per-order, cancel, delete, screens); `tests/mail_merge_hrefs_test.php`.
