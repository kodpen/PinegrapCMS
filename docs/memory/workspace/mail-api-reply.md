---
name: mail-api-reply
description: The API's plain-text answer to an e-mail conversation (POST …/reply without html or file_ids): sent at once by ws_mail_reply_send(), the bounce list and the hourly limit it keeps (#9674), its refusals
---

## What does a plain-text answer through the API do?
- `ws_api_mail_conversations_reply()` (`api_mail.php`): with `html` or `file_ids` it takes the queue as the card's drawer does (`mail-reply.md`); with text only it runs `ws_mail_reply_check()`, `api_dry_run_stop()`, then `ws_mail_reply_send()`.
- `ws_mail_reply_send()` calls `email()` at once (no queue), only to the customer, the same text; `not_sent` carries the server's words. Its out item reads sent.

## Which limits does it keep?
- The same as every mail the Workspace sends (#9674): `ws_mail_reply_check()`, after `ws_mail_reply_target()`, runs `ws_mail_send_limits()` with the customer's address and the writer's `ws_mail_send_last_hour()`.
- A customer address on the bounce list (`pg_mail_address_blocked()`) is `blocked`; a writer with `WS_MAIL_SEND_HOURLY` (30) out items in the last hour is `hourly`. The words are a new mail's (`ws_mail_reply_error_text()` hands both to `ws_mail_send_error_text()`).
- Why: before #9674 the plain-text answer skipped both, while the screen and the API's HTML answer were refused; the queue's own gate is never reached on this path.

## Which refusals does it answer?
- `ws_api_mail_reply_refusal()`: 422 `validation_failed` on `text` (empty, too_long); 422 `validation_failed` for `blocked`; 429 `rate_limited` for `hourly`; 403 no_access; 409 not_sent; everything else 409 cannot_send.

## Tests?
- `tests/workspace_mail_reply_test.php`: `test_workspace_mail_reply_keeps_the_send_limits` (the check's wiring, the refusals, the words).
