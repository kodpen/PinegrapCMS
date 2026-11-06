---
name: mailchimp-sync-running-claim
description: How the MailChimp sync in the general job keeps two overlapping runs from syncing at once.
---

## How does a MailChimp sync start?
- `mailchimp_sync()` (`mailchimp.php`, called by `job.php` when MailChimp and commerce are on) claims `config.mailchimp_sync_running` with one statement, `UPDATE config SET mailchimp_sync_running = '1' WHERE mailchimp_sync_running = '0'`, and runs only when that changed a row. Every exit sets it back to 0.
- No changed row (or a failed query) means another run holds it: the "sync is already running" line goes to the Site Log and the run returns an error.

## Why one statement?
- General job runs overlap. The flag used to be read with a SELECT and set with a separate UPDATE, so two runs that started together both saw 0 and both pushed the same products and orders to MailChimp. Fixed in the bug-hunt PR for this note (`tests/mailchimp_sync_claim_test.php`).

## What if a run dies mid-sync?
- The flag stays 1 and later runs keep refusing until it is cleared by hand (`UPDATE config SET mailchimp_sync_running = 0;`, named in the log line). There is no expiry: the column is a TINYINT flag, not a time.
