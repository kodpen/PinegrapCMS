---
name: api-campaign-create
description: POST /campaigns (plain-text draft) and POST /campaigns/{id}/schedule through the create screen's recipient pick and insert (fn/email_campaign_create.php); why HTML is not offered
module: email_campaign_create
---

## Which write path does POST /campaigns use?
- `includes/fn/email_campaign_create.php`, shared with `add_email_campaign.php`. `pg_email_campaign_pick_recipients($user, $purpose, $groups, $entered)` takes the included groups' addresses (opted-in contacts only for a commercial campaign, `pg_email_campaign_recipient_query()`), removes every address of the excluded groups, adds the entered address, and keeps each address once with its first contact. It skips a group the user may not reach. For roles below 3 it makes an entered address that nobody has into a contact and audits it. `pg_email_campaign_insert($values, $picked, $user_id)` writes the campaign (body wrapped at 900, status `ready` or `paused`), its recipients and the group xref.
- The checks are the screen's `pg_email_campaign_field_errors()` (subject, from address, reply-to). On top of those, the API refuses with 422 a group id that does not exist or that a role-3 owner was not given; the screen silently passes over such a group.

## What does a call do?
- POST /campaigns writes a manual, plain-text campaign kept `paused` (a draft). Nothing is sent. `api_dry_run_stop()` sits before the pick, because the pick may create a contact. A pick that finds no address is a 422 after the rehearsal point.
- POST /campaigns/{id}/schedule moves a paused campaign to `ready` with `send_at` (empty means as soon as the job runs). Any other status is 409 `campaign_not_paused`. The job or Send now does the sending, and `campaign.sent` follows when the campaign completes (`api-sms-campaigns.md`).

## Why no HTML campaign over the API?
- The screen checks the page with `check_view_access()` and renders it with `get_page_content()`, and both read the logged-in panel session (`USER_ROLE`, `USER_LOGGED_IN`), which an API caller does not have. HTML through the API waits until that is settled; until then such a campaign is made on the screen.

Tests: `tests/api_campaigns_test.php`, `tests/email_campaign_recipients_test.php`, `tests/audit_contact_writers_test.php`.
