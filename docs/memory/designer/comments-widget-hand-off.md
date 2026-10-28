---
name: comments-widget-hand-off
description: How the comments widget and the classic comments block share a page: the page type left to the classic block, the per-pass claim record, what a claim silences, lists that never claim
---

## Which pages does the widget leave to the classic block?
- `pg_comments_widget_classic_thread($page_type, $has_record_widget)` (pure, `includes/fn/widgets_comments.php`): a `form item view` page only. There the widget prints nothing and does not claim the page, so the classic block keeps drawing the `?r=` record or the search index's form id. Claiming it, the widget had printed nothing while silencing the block.
- A `catalog`, `catalog detail` or `calendar event view` page is the widget's since slice 20a: it reads the item in the address as the classic block does, with the review rules ([[comments-widget-item-threads]]). Until then (#2410) it was left to the classic block: claiming first, the widget had shown every item's comments on such a page and posted new ones to the page's own thread ('' / 0).

## When does it claim, and what does a claim do?
- After that check, before every other refusal: comments off, a record the visitor may not see, a form_item_view below the top level (fail closed). The widget owns the thread even when it then prints nothing; a second comments widget on the page finds the claim and prints nothing.
- `get_page_content()` starts each widget pass with an empty record (`pg_comments_widget_pages(array())`) and restores the previous one after it, so an e-mail or another page drawn meanwhile neither adds to it nor clears it.
- The classic block skips a page in its pass's record (`pg_comments_widget_drawn()`): no second list, no second add_comment form-state reader, no System-region append; a `<!--pg-comments-block-->` marker is emptied.
- In a comment e-mail the live claim is never reached: the e-mail branch claims the e-mail page only after it drew the comment with its link, and that claim also leaves out the e-mail's fixed comment markup (`pg_comments_email_keeps_fixed_markup()`, [[comments-widget-email]]).

## Does a list of recent comments claim the page?
- No. `_render_system_widget_comments()` hands a widget in the recent mode (`pg_sw_widget_kind()` `comments_recent`) to its own renderer before the e-mail branch, the classic check and the claim: it reads no form state and its Messages node is `comments_recent`, so a topic page's thread or classic block drawn after it keeps its form and a refused post's errors ([[comments-widget-recent]]).

## Which page id is it?
- `pg_sw_render_context()['page_id']`, set by `get_page_content()` for the drawn page; never `_pg_sw_current_page_id()` (0 on the home page, in e-mails and the search index, cached per request). The editor's Preview sets the saved page's id in a claim record of its own ([[comments-widget-preview]]); no page (an unsaved one): nothing drawn, nothing claimed.

## How is it checked?
- `tests/comments_widget_test.php`: the claim registry, the classic-thread table, the renderer's order (classic check, claim, the other refusals), the wiring in `get_page_content.php`.
