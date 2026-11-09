---
name: api-sms-campaigns
description: GET /campaigns/sms (campaigns:read) as My SMS Campaigns lists them, the user-role rule, the queue figures read in one batch, and the campaign.sent event for e-mail and SMS campaigns
---

## What does GET /campaigns/sms answer?
- The `sms_campaigns` rows (`platform/sms-campaigns.md`), newest first, cursor paged, optional `status` (draft, ready, queued, cancelled). The rows come from `api_sms_campaign_present()` in `includes/api/resources/campaigns.php`: name, body, status, send_at, queued_at, queued, refused, `outbox`, contact_group_ids, excluded_contact_group_ids, created_by, created_at, updated_at.
- `outbox` ({sent, waiting, failed}, the named object `SmsCampaignOutbox?`) is filled only for a queued campaign. It holds what `sms_outbox` still keeps under the references `smscampaign:<id>:%`, grouped the way the screen groups it (waiting = queued + sending, failed = failed + uncertain). The figures for the whole page come from one read, so there is no query per row. queued and refused are the lasting counts.
- The user role (3) gets only the campaigns it created (`api_sms_campaign_owner_filter()`), as on `view_sms_campaigns.php`. The route answers 503 `service_unavailable` before the 2026.11.0 tables exist.
- The handler requires `includes/fn/sms_campaigns.php` itself: functions.php does not load that module, and without it the readiness check found no function, so the route answered 503 on every site until PR #7967 (found by the API audit's live run).

## When is campaign.sent announced?
- The payload is built by `pg_campaign_sent_event()` (`includes/fn/events.php`): {id, channel email|sms, type manual|automatic}, identifiers only.
- An e-mail campaign announces it when its status moves to complete. Two places do that: `email_campaign_job.php` and `send_email_campaign.php` (Send now). Each checks that its UPDATE changed one row, so the campaign is announced once, by the run that finished it. Automatic campaigns announce it too.
- An SMS campaign announces it in `pg_sms_campaigns_run_due()` after the job queued it.

Tests: `tests/api_sms_campaigns_test.php`.
