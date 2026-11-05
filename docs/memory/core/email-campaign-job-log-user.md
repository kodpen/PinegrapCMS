---
name: email-campaign-job-log-user
description: Which user email_campaign_job.php writes into its activity log lines: the manager of a web run, else UNKNOWN (no session read in a scheduled run)
---

# Campaign job log user

## Who do the job's log lines name?
- `$email_campaign_job_log_user`, set once after the access gate: the signed-in manager's username for a web run, else `UNKNOWN`.
- The "sent e-mail(s) to N recipient(s)" and "skipped N recipient(s) whose address bounced" lines pass it.

## Why not `$_SESSION['sessionusername']`?
- A scheduled run (cron, the general job's dispatcher) has nobody signed in: the key is missing and every run that sent or skipped a recipient raised an "Undefined array key" warning.
- `tests/email_campaign_job_log_user_test.php` fails for a log line that passes the session user unguarded.
