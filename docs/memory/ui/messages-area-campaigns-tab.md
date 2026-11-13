---
name: messages-area-campaigns-tab
description: Communication › Campaigns (MA3, #9215): e-mail and SMS campaigns in one list, the channel and state filters, the merged row ids and Delete Selected, New campaign's channel menu
module: messages_campaigns
---

## What does the Campaigns tab list?
- E-mail and SMS campaigns in one printed list (`includes/fn/messages_campaigns.php`), newest change first; columns Name, Channel, To, Status, Send Time, Result, Last Modified. Filters: channel (All channels, E-mail, SMS; SMS only with `pg_sms_campaigns_ready()`) and state: Active (e-mail ready, paused; SMS draft, ready) or Finished (e-mail complete, cancelled; SMS queued, cancelled).
- A row id is the campaign id * 2, + 1 for SMS (`pg_messages_campaign_row_id()`); Delete Selected posts `campaigns[]` back to the screen, which splits them and calls `pg_email_campaigns_delete()` and `pg_sms_campaigns_delete()`. `delete_email_campaigns.php` keeps its address and calls the e-mail one. A role 3 user lists and deletes only their own.
- Manual campaigns only: since MA2b (#9275) an automatic e-mail campaign (one per profile send) is a single message of the Sends tab (`plans/messages-area-decided.md`).
- "New campaign" asks the channel in a menu (E-mail `add_email_campaign.php`, SMS `edit_sms_campaign.php`); a plain button while e-mail is the only channel. Role <= 2 also gets Campaign settings (the Campaigns card).
- The edit screens' notices and the SMS screen's returns go to this list.

Test: `tests/messages_campaigns_test.php`.

Related: docs/memory/ui/messages-area-shell.md
