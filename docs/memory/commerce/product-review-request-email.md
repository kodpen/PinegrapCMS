---
name: product-review-request-email
description: The e-mail asking a buyer for a review (order_review_request; 2026.11.0, PR #TBD): what starts it, once per order, items, mail-merge fields, delete; the verified filter in moderation
---

## What starts it?
- `update_order()` (`update_order.php`), when a save ships the order: right after the `order_shipped` campaign it calls `pg_review_request_start()` (`includes/fn/reviews.php`), which runs the enabled profiles of the action `order_review_request` through `create_auto_email_campaigns()`. A profile has no item; its delay is the usual "after" schedule (e.g. 7 days).
- An order that never ships (downloads, services) gets no request.

## When is nothing started?
- The review columns are missing (`pg_reviews_ready()`), the order was placed without an account (`orders.user_id = 0`: the review form signs the reader in with that account), or it is not bought by the form's rule (`pg_review_bought_sql()`: complete/exported, not an unpaid bank transfer).
- The order already has an `email_campaigns` row of the action, in any status: a sent, waiting or cancelled request ends it. A shipment while no profile is enabled creates no row, so a later shipment of the order may still start it.
- No product page (`pg_sw_catalog_detail_page_name(0)`), or no item with an address.

## Which items does it ask about?
- `pg_review_request_targets()`: the order's lines with a product, lines saved for later left out (`pg_order_item_active_sql()`), in line order. A product sold in an enabled group with `display_type = 'select'` is reviewed on the group (the lowest group id), as the product page takes its reviews; else the product, if enabled. One entry per item; an item without an address is skipped.
- Name: `short_description`, else `name` (for a product that is the SKU).

## Which mail-merge fields are there?
- `^^order_number^^`; `^^review_url^^`: the first item's form, `<site>/<product page>/<address>#software_add_comment`; `^^review_links^^`: one line per item, `Name: address`.
- Filled in when the campaign is created. In HTML format the value is escaped and line breaks become `<br>`, so for a button write `<a href="^^review_url^^">` (the address exactly as the field, in the source view, nothing in front of it). `create_auto_email_campaigns()` leaves an `href` that starts with `^^` alone: no site address in front, no tracking code.
- The body page also gets `order_id` and `review_targets` in its data.

## Where does the link land?
- On the page products open on. A legacy catalog detail page has the review form at `#software_add_comment`; a visual-designer product page has no review form until plan step 2c (`plans/storefront-conversion-pack.md`).

## What happens when the order is deleted?
- `delete_order()` cancels its `ready`/`paused` `order_abandoned` and `order_review_request` campaigns.

## How does Comment Moderation filter reviews?
- `view_comments.php` toolbar menu: All comments / Verified purchases (`verified_order_id > 0`) / Not verified (`= 0`), `?verified=` '' / yes / no, kept in `$_SESSION['software']['comments']['verified']` with the search (`pg_review_verified_filter_sql()`). Only with the review columns.

## Where are the tests?
- `tests/reviews_test.php`: the address, the fields, the items from rows, the filter SQL, and source pins for the trigger, the once-per-order query, delete, the profile screens and the mail-merge `href`.
