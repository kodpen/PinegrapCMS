---
name: comments-widget-item-threads
description: The comments widget on catalog, catalog detail and calendar event view pages (slices 20a, 20b): the item in the address, its check, the product review rules, the item's flags and name, the canvas
module: comments
---

## Which thread does the widget show on such a page?
- The item the classic block of `get_page_content.php` would pick, from the pure `pg_comment_address_item($page_type, $facts)` (`includes/fn/comments.php`); `pg_comment_address_item_facts()` reads the address.
- `catalog`: the product group the address names, else the page's own group, else the top-level one. An address naming a product or nothing: no thread (the classic block posts a product's id as a group there).
- `catalog detail`: the product or group the address names; no `/` in the address: the page's own thread (`''` / 0, only those comments, as the classic block reads it); not found: no thread.
- `calendar event view`: the event the render's sender names (`calendar_event_id` in `pg_sw_render_context()`, the search index), else `?id=`; none: the page's own thread. `?recurrence_number=` shows the same event and shares its thread, as on the classic block; the comment links keep it (they copy the address).
- A top-level form_item_view widget still decides first (that record's thread).

## How is it checked?
- After the claim, an item thread is asked of `pg_comment_thread_verify()`, the rule `add_comment.php` applies: an item the page does not show (an event outside the view's calendars, unpublished, a product that is gone) prints nothing. No thread: nothing.
- The forms post the item's type and id; watchers, allow-new and replies read that thread.

## Which review rules does a product thread keep?
- Read: `pg_comments_rows()` sees a review thread (`pg_review_gate_applies()`): verified only, newest first, no featured (#2426, `commerce/product-review-order.md`). Badge and store reply were already row flags and tokens.
- Write: for a live render, not staff, the widget asks `pg_review_eligibility()`. `sign_in` sets `must_sign_in` (with `__login_url`); `not_bought` and `reviewed` set `comments_closed` and put `pg_review_refusal_message()` in `__comments_closed_message`, so every design shows the reason where it shows the closed note (`pg_comments_widget_thread_flags()` fact `review_refusal`).
- `add_comment.php` checks again, writes `verified_order_id` and recounts the rating (`pg_review_comment_changed()`); unchanged.

## Which flags and token does an item thread give? (20b)
- `is_item_thread` (fact `item`: a product, group or event thread) and `is_review_thread` (fact `review`: `pg_comments_rows()` read a review thread; implies the item flag), in `pg_comments_widget_thread_flags()` and its JS twin, which also took the `review_refusal` rules. They follow the facts, e-mail included.
- `__comments_item_name`: `pg_comment_item_name()` (`includes/fn/comments.php`, one read per item and request) -> pure `pg_comment_item_label()`: a product's or group's short description as text, else its name; an event's name; '' for a page or record thread. Escaped once with the other tokens.
- A list of recent comments switches all three off (`pg_comments_mode_off_bindings()` and twin).

## What does the editor show? (20b)
- The canvas reads the thread from the page type on screen (`_sdCommentsPageThread()`): catalog detail = a product's reviews, catalog = a group, calendar event view = an event. States: [[comments-widget-canvas]].
- Canvas Preview and Make designable say whose comments such a page shows and where e-mails about them link (`_sdCommentsPageThreadText()`).

## What stays out?
- Recent lists refuse those pages as sources ([[comments-widget-recent-gates]]).

- Comment e-mails about an item: [[comments-widget-email-items]].

## Where is it tested?
- `tests/comments_widget_threads_test.php`: the address table, the review and item flags, the name, the renderer's order; the canvas: `comments_widget_designer_test.php`.

Related: [[comments-widget]], [[comment-thread-verify]]
