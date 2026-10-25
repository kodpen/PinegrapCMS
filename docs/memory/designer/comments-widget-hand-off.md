---
name: comments-widget-hand-off
description: How the comments widget and the classic comments block share a page: the pages left to the classic block, the per-pass claim record, what a claim silences
---

## Which pages does the widget leave to the classic block?
- `pg_comments_widget_classic_thread($page_type, $has_record_widget)` (pure, `includes/fn/widgets_comments.php`): a `form item view` page always; a `catalog`, `catalog detail` or `calendar event view` page (`pg_comment_classic_item_types()`) unless a top-level form_item_view widget decides the thread (the classic block then reads that record's thread too).
- There the widget prints nothing and does not claim the page, so the classic block keeps drawing the item in the address (product, product group, event, the `?r=` record or the search index's form id) with its review gate, and the search index keeps each event's and record's own comments.
- Why: the widget serves page-level and designed-record threads only; per-product and per-event threads are deferred (owner, 2026-10-23). Claiming first, it had shown every item's comments on such a page and posted new ones to the page's own thread ('' / 0), and on a `form item view` page it had printed nothing while silencing the block.

## When does it claim, and what does a claim do?
- After that check, before every other refusal: comments off, a record the visitor may not see, a form_item_view below the top level (fail closed). The widget owns the thread even when it then prints nothing; a second comments widget on the page finds the claim and prints nothing.
- `get_page_content()` starts each widget pass with an empty record (`pg_comments_widget_pages(array())`) and restores the previous one after it, so an e-mail or another page drawn meanwhile neither adds to it nor clears it.
- The classic block skips a page in its pass's record (`pg_comments_widget_drawn()`): no second list, no second add_comment form-state reader, no System-region append; a `<!--pg-comments-block-->` marker is emptied.
- In a comment e-mail the live claim is never reached: the e-mail branch claims the e-mail page only after it drew the comment with its link, and that claim also leaves out the e-mail's fixed comment markup (`pg_comments_email_keeps_fixed_markup()`, [[comments-widget-email]]).

## Which page id is it?
- `pg_sw_render_context()['page_id']`, set by `get_page_content()` for the drawn page; never `_pg_sw_current_page_id()` (0 on the home page, in e-mails and the search index, cached per request). The editor's Preview sets the saved page's id in a claim record of its own ([[comments-widget-canvas]]); no page (an unsaved one): nothing drawn, nothing claimed.

## How is it checked?
- `tests/comments_widget_test.php`: the claim registry, the classic-thread table, the renderer's order (classic check, claim, the other refusals), the wiring in `get_page_content.php`.
