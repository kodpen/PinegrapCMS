---
name: comments-widget-two-forms
description: A product page's reviews and questions widgets keep apart: form state, Messages, form id and anchor, which comment each form answers, the Reply script's scope
module: product_questions
---

## Why must the two forms keep apart?
- The store, boutique, bookshop and hello templates place a reviews widget above a questions widget on the product page; both draw a form posting to add_comment.php. They once shared one liveform (`add_comment`), one Messages name and the id `software_add_comment`: the reviews widget, drawn first, printed a refused question's error, refilled its own form and used the state up (#7567), and staff's Reply under a question filled the reviews form, storing the answer as a hidden plain comment with no answer e-mail (#7566). Fixed in #PR.

## What names does each form use?
- `pg_product_question_form_names($kind)` (pure): kind `question` gives state `add_question` and anchor `software_add_question`; anything else `add_comment` / `software_add_comment`.
- add_comment.php opens its liveform under that state (from the posted `kind`) and sends each refusal back to that anchor.
- `_render_system_widget_comments()` reads that state, names its Messages node after it (`_pg_inject_messages_node()`, `pg_comments_stamp_form_messages($tree, $name)`), gives the form that id (form ctx `anchor`) and ends its reply and cancel links there (`pg_comments_widget_link_values(..., $anchor)`).

## Which comment may each form answer?
- `pg_product_question_reply_fits($questions, $parent_kind)` (pure): the questions form only a question; the reviews form never a question or an answer. A `?reply_to=` or kept `parent_id` of the other kind is dropped (`pg_product_question_state()` reads the kind).

## How does the Reply script pick the form?
- `software.init_comment_actions()`: the nearest ancestor of the control that holds a `form[data-pg-comment-form]` is the scope; the form, `[data-pg-comment-reply-to-name]` and `[data-pg-comment-replying]` are looked up inside it (src and hand-made min twin).

## Where is it tested?
- `tests/product_questions_test.php` (names, reply rule, wiring of add_comment.php, the renderer and both JS files); `comments_widget_test.php` (form id, reply link anchor).
