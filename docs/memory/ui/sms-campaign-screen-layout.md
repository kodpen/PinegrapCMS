---
name: sms-campaign-screen-layout
description: The SMS campaign screen (edit_sms_campaign.php) on the wide edit layout: what sits in the form column, the status column and the save bar
module: sms_campaigns
---

## How is the SMS campaign screen laid out?
- `edit_sms_campaign.php` (create and edit) follows the wide layout (`edit-screen-wide-layout.md`, step B11, PR #6722) through the e-mail campaign's `pg_email_campaign_layout($form, $side, $buttons)` (`email-campaign-shared-form.md`).
- Form column: SMS Message (name, text with the character counter) and SMS Message To My Contact Groups (the Ignore/Include/Exclude table).
- Sticky status column: SMS Message Delivery. Editable: a Status row on a saved campaign (draft or scheduled), When and the date and time, both full width. Read-only (queued, cancelled): Status, Send Time, Queued for sending as rows, then the Result text.
- Save bar: Save and, for a scheduled campaign, Cancel Campaign; a read-only campaign has none, so the layout leaves the bar out.

## Where is the row helper?
- `pg_sms_campaign_status_row($label, $value)` in `includes/fn/sms_campaigns.php`: a `col-12` flex row inside the card's `.row`, label escaped, value HTML, a line under it.
- Tests: `tests/sms_campaign_screen_test.php` (the row, the screen on the shared layout, no half-width rows, groups before delivery).
