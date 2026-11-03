---
name: product-questions-output
description: Product questions outside the widget: the asker's mail when answered, API kind and answer_of, the Question/Answer JSON-LD on the Product, the templates' questions widget
module: product_questions
---

## Who is mailed when a question is answered?
- The asker, once, by `pg_product_question_answer_mail()`: from `edit_comment.php` when the Answer box adds an answer, from `add_comment.php` when staff answer published from the page. Due (`pg_product_question_answer_mail_due()`, pure): question and answer published, the asker signed in (a guest left no address) with an address, and not the one who answered. Plain text, queued; the link uses `HOSTNAME_SETTING`.
- Not mailed: an answer first held and published later in moderation (its row is an answer, not a question).

## What do the API and the structured data say?
- API Comment: `kind` (comment/question/answer, `api_comment_kind()`), `answer_of` (null but on an answer), `GET /comments?kind=`; `GET /products/{id}/reviews` asks `kind=comment`, so questions are no reviews.
- Product JSON-LD: `subjectOf` = the newest ten answered questions as `Question` with `acceptedAnswer` (`pg_product_question_jsonld()`, pure `pg_product_question_jsonld_items()`), only when `pg_product_question_markup_shown()`: the rating rule without stars, on a page that places a questions widget (`pg_product_question_page_has_widget()`).
- The component census types a questions widget `comments_questions`, so it is never a second thread widget (no save warning, no `pg_comments_page_has_widget()`).

## What do the templates add?
- Store, boutique, bookshop, hello: widget `product_questions` (shows questions, newest, threaded depth 1, 10 a page, no watch box) in a "Questions" section under Reviews on the product page.

## Where is it tested?
- `tests/product_questions_test.php` (mail text and rule, JSON-LD items, census kind, API kind, wiring) and `design_template_shop_comments_test.php` (the template widget). Model and widget: [[product-questions]].
