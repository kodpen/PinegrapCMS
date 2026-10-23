---
name: comment-thread-verify
description: Which thread a posted comment joins (pg_comment_thread_verify()): a page with a form_item_view widget takes only the record it shows; a refused post goes back to its form with the text kept
---

## Which thread may a posted comment join?
- A thread is (page_id, item_type, item_id); the form sends all three as hidden fields, so `add_comment.php` asks `pg_comment_thread_verify($page_id, $page_type, $item_type, $item_id)` (`includes/fn/comments.php`) before validating or storing. Its pure part is `pg_comment_thread_decision()`.
- Legacy page type `form item view`: only `submitted_form`; its `form_item_view_pages` row decides who may see the record. A widget on that page is not asked (`get_page_content.php` does not ask it either).
- Any other page with a top-level form_item_view widget (`pg_sw_record_comment_context()`, any page type): only `submitted_form`, with an id the widget finds in its own custom form and lets this visitor see (access setting AND record filters, no e-mail exception). It is stored under the record's id. This matches what the page draws: its comments are the record's, or none.
- Any other page: the posted pair as it came, except `submitted_form`.

## What was the symptom?
- A comment posted with an empty item type (or `product`) to a designed record page landed on the page instead of the record: stored on the page's own thread, which that page never draws, yet sent to moderation and the administrator e-mail as a comment on the page. The old check asked the widget only when the post said `submitted_form`, so `item_type=''` skipped it and the record visibility gate with it.

## How is a refused post answered?
- Designed record page (refusal `item_type` or `record`): `log_activity()`, `$liveform->add_error()` and the usual redirect to `send_to#software_add_comment`, so the visitor sees the message above the form with what they typed. The posted `item_type`/`item_id` are dropped from the form state (`set_field(..., array())`): a hidden field prints the session value first, so the redrawn form would keep the refused thread and fail again.
- A record the visitor may not see and a record of another form get the same sentence ("you do not have access to view that submitted form"); only the log line differs. Such a page draws no comment form, so the message shows the next time the form is drawn.
- Classic pages keep `output_error()` (refusal `page_type`), at the same place in the checks as before.

## What is not checked yet?
- On a classic catalog, catalog detail or calendar event view page a product, product group or calendar event id is taken as posted; the classic branch of `pg_comment_thread_decision()` is where that check goes (planned in [[designable-comments]]).
- `add_or_remove_watcher.php` still asks the widget only for a posted `submitted_form`.

## Where is it tested?
- `tests/comments_test.php`: the table page type x widget x item type, the record rules, the classic pass-through, and that `add_comment.php` asks before the redirect and the INSERT.

Related: [[account-widget-forms]], [[record-view-filters]], docs/memory/core/fn-module-map.md
