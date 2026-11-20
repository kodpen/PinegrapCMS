---
name: sms-campaigns-module-load
description: functions.php does not load fn/sms_campaigns.php: a reader of SMS campaigns loads it itself, or a function_exists() check drops them silently (Sends lost them)
module: sms_campaigns
---

## Who loads includes/fn/sms_campaigns.php?
- Not functions.php. Each user loads it: `edit_sms_campaign.php`, `view_email_campaigns.php`, `job.php`, the campaigns API, and since the Sends bug hunt `includes/fn/messages_sends.php` and `includes/fn/messages_calendar.php`.

## What goes wrong without it?
- Nothing visible: Communication › Sends asked `function_exists('pg_sms_campaigns_ready')`, found it missing and left the SMS campaigns branch out, so no SMS campaign was listed, filtered or drawn on the calendar. A posted Delete for such a row would have called the undefined `pg_sms_campaigns_delete()`.
- A new reader of SMS campaigns adds `require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');` instead of a `function_exists()` guard. `tests/messages_sends_test.php` checks the two modules.

Related: [[sms-campaigns]], [[messages-area-sends]]
