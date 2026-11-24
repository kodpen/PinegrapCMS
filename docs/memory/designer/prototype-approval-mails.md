---
name: prototype-approval-mails
description: The approver's answer mail (at most one an hour per link) and the "Answer by" reminder of the general job (PA5): when each goes, what it counts, what it carries
module: prototype_review_mails
---

## Where is the code?
- `includes/fn/prototype_review_mails.php`; pure parts tested in `tests/prototype_review_mails_test.php`. The receipt of a verdict stays `pg_proto_verdict_receipt()` (`prototype-approval-review.md`).
- All three mails go only to the address the designer typed on the link (`approver_email`), queued, plain text, from the site's address; the link's address when the site key reads it back, never the password; no consent question (the plan's architect answer).

## When does the answer mail go?
- `pg_proto_comment_answer()` calls `pg_proto_answer_mail($link_id)` after an answer is saved: a live link (`pg_proto_link_state()` ok) asking approval with a valid address.
- It counts the designer's answers on the link since its guests last wrote (the newest guest comment or verdict, `pg_proto_answers_since_guest()`) and says "There are N new answers…".
- At most one an hour per link: `waf_rate_exceeded()` subject the link id, scope `prototype-answer-mail`, limit and window from `pg_proto_answer_mail_rate()` (1, 3600), as the bell line's `prototype-comment-bell`. The counter is touched only when a mail could go, so a link without an address spends nothing. Answers within the hour go in the next mail's count.

## When does the "Answer by" reminder go?
- The general job calls `pg_proto_answer_by_reminders_run(time())` before the volunteer pass. It runs once a day between 09:00 and 20:00 site time (`pg_proto_reminder_pass_open()`), claiming `config_text` `prototype_reminders_at` first (`platform/job-pass-time-claim.md`).
- It reads links with `approval` 1, `answer_by` tomorrow, an address, not revoked, no bundle (at most 500); skips expired ones; mails when `pg_proto_answer_by_reminder_due()` holds.
- Undecided pages (`pg_proto_undecided_pages()`): the design's draft pages the link shows (`pg_proto_link_page_shown()`) with no standing verdict or one given to an earlier draft ("Changed since your last review"). Approve and Request changes on the current draft both count as decided.
- A date set or changed after that day's pass gets no reminder.

Related: docs/memory/designer/prototype-approval-review.md, docs/memory/designer/prototype-approval-fingerprints.md, docs/memory/plans/prototype-approval-steps.md
