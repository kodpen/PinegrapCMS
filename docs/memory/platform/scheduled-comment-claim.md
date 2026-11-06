---
name: scheduled-comment-claim
description: How job.php publishes scheduled comments: a conditional claim (pg_comment_schedule_claim_sql) so overlapping job runs never send the comment e-mails twice
module: comments
---

## How does the general job publish a scheduled comment?
- `job.php` reads the unpublished comments whose `publish_date_and_time` has come, then claims each with `pg_comment_schedule_claim_sql($id, $due)` (`includes/fn/comments.php`): the UPDATE publishes it only `WHERE published = '0' AND publish_date_and_time = <the time read>`.
- Only when exactly one row changed does it update `submitted_form_info` and send the administrator, form submitter and watcher e-mails; the site log counts only claimed comments.

## Why the claim?
- job.php has no lock of its own: a slow tick (visitor table rebuild, a slow SMTP server) and the next cron tick, or a manager opening job.php by hand, overlap. Before the claim both runs read the same due comment, both updated it unconditionally and both sent every e-mail, so administrators and watchers got each scheduled comment twice.
- The time in the WHERE also leaves alone a comment whose schedule was changed or cleared between the read and the claim.

## Where is it tested?
- `tests/comment_schedule_claim_test.php`: the claim's conditions and escaping, and that job.php checks `mysqli_affected_rows()` before the first e-mail.
