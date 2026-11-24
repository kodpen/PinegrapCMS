---
name: feedback-answer
description: Satisfaction survey's message and answer page (FD4a): pg_feedback_message_send(), e-mail buttons, SMS kind, feedback.php, the page-side score write, 7-day change, review invitation, Don't ask me again
module: feedback_answer
---

## How does a survey go out?
- `pg_feedback_sender()` loads `includes/fn/feedback_answer.php` and gives `pg_feedback_message_send($row, $survey, $reminder)`; the job's pass calls it for a due row (already claimed as sent) and for the reminder (`feedback-send-pass.md`). False cancels the row.
- Texts in the site's first language (`pg_feedback_texts_in()`: the survey's text, the first language's, else `pg_feedback_default_texts()`).
- Links: `pg_feedback_links()` signs `answer` and `stop` tokens at the row's `due_at`, so the answer link's hash is the one the queue stored. `feedback.php?t=<answer>&s=<score>` per score, `feedback.php?x=<stop>`; host `HOSTNAME_SETTING`.
- E-mail: `pg_feedback_mail_html()` with links (a link per score, the words at both ends of the scale, the comment's label, a quiet "Don't ask me again"), subject the question ("Reminder: …" for the reminder), queued through `email()`. No promotion, no review invitation (poll 198).
- SMS: `pg_feedback_sms_text()`: store name, question, the page link. A service survey goes as SMS kind `feedback` (transactional); a commercial one as `marketing`, the only commercial kind (`sms-marketing-kind.md`, pinned by `tests/sms_test.php`), so it gets the identity line and the İYS check at queue and send, as the flow box does. Reference `feedback:<id>` (`:r` for the reminder), expires in a day.
- WhatsApp: the survey's approved template, kind `feedback` (`feedback-whatsapp.md`); `pg_feedback_message_channels()` is e-mail, SMS and WhatsApp.

## What does the answer page do?
- `feedback.php` (root): `no-store`, `noindex`, `no-referrer`, `DENY`; in `waf_sensitive_scripts()` (30 a minute, link guessing). Drawn with `output_header_secure()` and `pg_secure_card()` like `booking_manage.php` and `tickets.php`: a root script has no site theme to draw in, so the look A parts (`pg-seg` radios for the scale, wrapping on a phone; `pg-input`; `pg-button`).
- A row is found by the answer token's check and an equal `token_hash` (`pg_feedback_answer_find()`); the stop link by its signature at the row's current `due_at` (`pg_feedback_stop_find()`). A wrong, old or closed link: one 404 card.
- `pg_feedback_answer_state()`: `open` (sent), `changeable` (answered less than 7 days ago), `answered`, `closed` (queued, cancelled, no answer).
- The GET writes nothing. `assets/js/feedback_answer.js` (served as written) posts `action=score` for the score the link chose (the page checks the form token itself and answers JSON); a mail scanner runs no script, so its visit never counts. The score-only write never overwrites an answer.
- "Send" posts score, comment (2,000 characters) and extra answers (`pg_feedback_answer_input()`: a choice kept in the first language's words, a short text 300) with `validate_token_field()`; a change within 7 days keeps the first `answered_at`.
- After the answer: a thank-you line, the review invitation (`pg_feedback_review_url()`, the same for every score: a delivery's first product review form, else Google's write-a-review page of `pg_grev_state()['place_id']`; none when the survey's switch is off or nothing is set), "Change my answer" while changeable.
- "Don't ask me again" at the foot of both states and in every e-mail: the stop page asks, its POST sets `stopped` on the row and cancels that person's queued rows (`pg_feedback_stop()`); the fatigue rule reads `stopped` for good.

## What follows a written answer?
- `pg_feedback_answered()`: `feedback.received`, and a low score's follow-up ([[feedback-followup]]). "Send me a test" still sends unlinked buttons (no row, no token).

Test: `tests/feedback_answer_test.php`.

Related: docs/memory/platform/feedback-send-pass.md, docs/memory/platform/feedback-rules.md, docs/memory/ui/messages-area-survey-edit.md, docs/memory/platform/feedback-loop.md
