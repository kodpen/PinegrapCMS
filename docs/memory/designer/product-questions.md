---
name: product-questions
description: Product questions on the comments system: kind question/answer and answer_of, the widget Shows choice and its own claim, the answer box, the unanswered filter
module: product_questions
---

## How is a question stored?
- A comment of kind `question` on a product's thread (the review thread: `pg_review_gate_applies()`); the staff answer a comment of kind `answer` on the same thread with `answer_of` and `parent_id` naming the question. Every older comment is `comment` (step 11.0.148, `idx_answer_of`; decided in #5047).
- `pg_product_questions_ready()` probes both columns; before the step nothing reads or writes them.
- A question has `verified_order_id` 0 and rating 0: the verified-only review list, the rating and the `product.review.created` event never see it.

## Who decides the kind?
- `pg_product_question_post_kind()` (pure) in `add_comment.php`: `comment` unless the step ran, the thread is a product's and the form posted `kind=question`; then staff replying to a question write its `answer`, a reply to a review stays a `comment`, anything else is a `question`. A question skips the purchase gate (`$is_product_review` false), takes no file and no stars.

## How does the widget show them?
- Display › Shows: Comments and reviews / Product questions (`cfg.shows`, `pg_product_question_widget_shows()`; JS `_sdCommentsDisplaySect()` hides Featured and Replies for questions).
- Questions mode draws nothing off a product thread or before the step; reads `pg_comments_rows(..., 'kind' => 'questions')` (never a review read), threaded, featured none, label "Question", no rating or file, only staff get a reply control and only on a question; the form posts `kind=question` (`pg_comments_form_hidden_html()` 7th argument).
- It claims `pg_comments_widget_claim($page_id, 'questions')`, apart from the comments claim, so a reviews widget and a questions widget sit on one product page; only the comments claim stands the classic block down.

## Where does staff answer?
- `edit_comment.php` on a question: an Answer box (`pg_product_question_answer_save()`: adds a published answer, changes it, or removes it when emptied); no store reply field on a question; deleting a question deletes its answers and their notifications (`pg_product_question_delete_answers()`), on the screen and through the API's `DELETE /comments/{id}` (#PR; before it the API left the answer, which then showed alone in the recent comments list and the panel).
- `view_comments.php`: filter "Product questions" / "Unanswered questions (n)" (`pg_product_question_filter_sql()`, count `pg_product_questions_unanswered_count()` over the reader's folders), a badge per row (`pg_product_question_badge()`).
- The bell's `new_comment` row of a question reads "A product question is waiting for an answer." and opens the comment's screen.

## What is said outside the page?
- The asker's mail, the API fields, the JSON-LD and the template widget: [[product-questions-output]].

## What is left?
- The comment e-mail page and the recent comments list draw every kind, questions included.

## Where is it tested?
- `tests/product_questions_test.php`: the kind decision, the filters, the thread split by kind (`pg_product_question_split()`), the badge, the claim, the hidden field, the mail text and rule, the JSON-LD items, the census kind, the API kind, the wiring; `design_template_shop_comments_test.php` the template widget. No running instance: unit and source tests only.
