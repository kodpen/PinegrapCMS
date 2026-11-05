---
name: email-campaign-send-claims
description: Send now and the campaign job claim recipients with one conditional UPDATE, so overlapping runs never deliver twice; follow-ups only from the run that finished the campaign
module: email_campaign_send
---

## How do Send now and the job avoid sending one recipient twice?
- With the retry columns (`pg_email_retry_ready()`) both claim before sending: `pg_email_campaign_claim_query($ids, $token)` sets `claimed_at` and `claim_token` only where `complete = 0`, `claimed_at = 0` and the row is due. One statement is atomic even on MyISAM, so each row goes to one run; the token says which rows a run won (`pg_email_campaign_claim_token()`).
- Send now reads only `claimed_at = 0` rows, keeps the ones it won, passes its token to `pg_email_campaign_skip_suppressed()` and `pg_email_campaign_record_failure()`, clears the claim when it marks a row complete and releases the rest at the end of the batch.
- Before #7057: Send now ignored claims. A manager pressing Send Campaign while a job run held the same recipients sent each of them twice.
- Without the retry columns the old flows stay: the job under `LOCK TABLES`, Send now unclaimed.

## What frees a claim a run left behind?
- `pg_email_campaign_stale_claim_query($campaign_id)` after 15 minutes. The job frees every campaign; Send now frees its own campaign first, so a claim of a closed or crashed window does not hold a recipient forever while the job is off.
- Rows a job run holds count as waiting in the Send now summary; the campaign stays open until they finish.

## Who queues the follow-ups of an automatic campaign?
- Only the run whose `UPDATE email_campaigns SET status = 'complete'` changed the row (`mysqli_affected_rows() === 1`), in both paths, as with the `campaign.sent` announcement. Before #7057, a job run that found the campaign already finished by another run still called `create_auto_email_campaigns()`, queuing the follow-up campaigns twice.
- Tests: `tests/email_campaign_send_test.php`.

Related: docs/memory/core/email-campaign-send.md
