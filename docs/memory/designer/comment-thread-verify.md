---
name: comment-thread-verify
description: Which thread a posted comment joins (pg_comment_thread_verify()): a record view page only its record, a classic catalog or event page only an item it shows; refusals after the page gates
module: comments
---

## Which thread may a posted comment join?
- A thread is (page_id, item_type, item_id), posted as hidden fields, so `add_comment.php` asks `pg_comment_thread_verify()` (`includes/fn/comments.php`) before storing. Pure part: `pg_comment_thread_decision()`.
- Page type `form item view`: only `submitted_form`; its `form_item_view_pages` row decides who sees the record (a widget there is not asked).
- Another page with a top-level form_item_view widget (`pg_sw_record_comment_context()`): only `submitted_form`, with an id the widget finds in its own form and shows this visitor (access setting AND record filters, no e-mail exception); stored under the record id.
- Any other page: its own thread (`''`, as posted) or an item type its comment block names (`pg_comment_classic_item_types()`): catalog `product_group`; catalog detail `product`, `product_group`; calendar event view `calendar_event`. Any other type is refused (`page_type`), an item the page does not show too (`item`). Stored under the item's integer id.

## Which item does a classic page show?
- `pg_comment_classic_item_shown()` follows how `get_page_content.php` picks the comment block's item:
  - product, product group: any that exists. The page finds it by the address name, with no tie to its own group, and draws the block under a disabled item too.
  - calendar event: exists, published, in a calendar of this view (`calendar_event_views_calendars_xref`); for any other one the screen ends in `output_error()` before the comments.
- Refused although the legacy block draws a form: an address naming nothing (a 404 page), a catalog address naming a product (the block posts `product_group` with the product's id), a crafted `?id=...&from=control_panel` on an event view page.

## What were the symptoms?
- Designed record page: `item_type=''` (or `product`) landed on the page's own thread, never drawn there, yet sent to moderation and the admin e-mail. The old check asked the widget only for a posted `submitted_form`.
- Classic pages took any product, group or event id as posted, on any page type: a `product` posted to a standard page was stored and listed there.

## When and how is a refusal answered?
- After the page's own gates (comments on, new comments allowed, login, `check_view_access()`): a visitor who may not comment gets the same answer whatever id was posted.
- `item_type`: log, `add_error()`, posted type and id dropped from the form state (`set_field(..., array())`: a hidden field prints the session value first), redirect to `send_to#software_add_comment`.
- `record`, `item`: log, `remove_form()`, `output_error()` with one sentence. The page draws no form for that record or item, and the session would carry the typed text and the refused thread onto the next record or item of the page.
- `page_type`: `output_error()`, as before.

## What is not checked yet?
- The page's own thread: `''` with any id is taken as posted.
- `add_or_remove_watcher.php` asks the widget only for a posted `submitted_form` and takes product and event ids as posted.

## Where is it tested?
- `tests/comments_test.php`: the table page type x widget x item type, the record and item rules, `pg_comment_classic_item_types()`, no query without a page, the `add_comment.php` wiring.

Related: [[account-widget-forms]], [[record-view-filters]], [[designable-comments]], docs/memory/core/fn-module-map.md
