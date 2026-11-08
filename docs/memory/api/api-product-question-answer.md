---
name: api-product-question-answer
description: POST /comments/{id}/answer writes, changes or removes the staff answer to a product question through the comment screen's save (comments:write, dry_run); refusals, the asker's mail
---

## What does POST /comments/{id}/answer do?
- The Answer box of `edit_comment.php` over the API (gap G2 of the audit #7967): body `text` (required, plain, at most 5000 characters as `pg_product_question_answer_text()` cuts). It calls `pg_product_question_answer_save()` (`includes/fn/product_questions.php`) with the key owner's id and username, so the answer shows the owner's name, as the screen shows the editor's.
- An empty `text` removes the answer and the question waits again. `text` is required so an empty string is always deliberate.
- Answer `CommentAnswer`: `question_id`, `result` (`added`, `changed`, `removed`, `unchanged` = nothing written) and `answer` (the Comment, null without one). `api_comment_answer_outcome()` works out the result before the write; a save that answers '' for a result other than unchanged is a 500.

## What does it refuse?
- 404 for no comment, 409 `not_a_question` for a comment of another kind (an answer, a review), 503 `service_unavailable` before `comments.kind`/`answer_of` exist (`pg_product_questions_ready()`).
- No folder rule: `comments:write` is in the roles 0-2 block of `api_owner_scopes()`, who pass `check_edit_access()` everywhere.

## What happens beside the write?
- A first answer (`added`) mails the asker through `pg_product_question_answer_mail()`, as the screen does; the dry run says whether in `detail.email` (signed-in asker with an address, not the owner).
- An activity log line names the application and key. No webhook: the screen announces none either.
- Tests: `tests/api_comment_answer_test.php`.
