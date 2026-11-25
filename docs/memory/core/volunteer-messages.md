---
name: volunteer-messages
description: Volunteer passes of the general job (VS5): unfilled-shift call, day-before reminder, document notices; channels, 09-20 hours, SMS kind volunteer, Shift calls switch
module: volunteer_messages
---

## Where is it?
- `includes/fn/volunteer_messages.php`, run by `job.php` through `pg_volunteer_job_run()` before the mail and SMS passes, only when `pg_volunteer_ready()`. Pure parts tested in `tests/volunteer_messages_test.php`. Built in #11250, step VS5 (`volunteer-shifts.md`).
- The board's "Call volunteers" (`pg_volunteer_shift_call()`) sends through the same `pg_volunteer_shift_call_send()`.

## When do calls and reminders go?
- Only between 09:00 and 20:00 site time (`pg_volunteer_send_hour()`, `PG_VOLUNTEER_SEND_FROM` / `_TO`): never at night, also from the board's button, whose drawer then says the call goes by itself later (`pg_volunteer_call_hint()`).
- Call: each open programme with a call channel, shifts not started within its call days (`pg_volunteer_call_due()`); the ids go into the shift's `called` by one UPDATE conditional on the list read, then the messages, so two runs never ask anyone twice. A full role asks nobody.
- Reminder: shifts starting tomorrow or later today (`pg_volunteer_reminder_due()`), also of a paused programme, whose shifts still run (#11338; before, a paused programme's volunteers got none); `reminded_at` is claimed first, then every signed volunteer not left is told, calls on or off.
- At most 200 shifts or documents a pass (`PG_VOLUNTEER_PASS_ROWS`), nearest first.

## By which channel?
- `pg_volunteer_message_channels()`: the volunteer's `call_channel` first when the programme's `call_channels` offers it and it reaches them (a mobile number for SMS and WhatsApp, an e-mail address), then the programme's other channels in the order e-mail, SMS, WhatsApp; the first that takes the message ends it. A reminder with no programme channel goes by e-mail (`pg_volunteer_reminder_allowed()`).
- SMS: kind `volunteer` (not commercial in `pg_sms_kinds()`, no consent asked, poll 207), reference `volunteer:<shift>:<volunteer>:<call|reminder>`, two hours to expire. WhatsApp: events `volunteer_call`, `volunteer_reminder` on the WhatsApp Messages card, `service` (a marketing template is refused), fields name, programme, shift, page.
- The volunteer's page link is added when their sent link is still live: the token is rebuilt from `link_issued_at` and checked against `link_hash` (`pg_volunteer_live_token()`); no new link is issued.

## What do the document notices do?
- A document of a volunteer not left, ending within 30 days, gets the 30-day and the 7-day notice once each (`pg_volunteer_document_notice()`), by e-mail to the volunteer and to each coordinator of the programme. Bits in `notice_sent`: 1 for 30, 2 for 7; the 7-day notice sets both. The bit is claimed before sending: a dying pass loses a notice, never doubles it.
- A document whose kind has a newer one valid longer is told about no more (#11338; before, the old one's notices went on after the volunteer brought the new one).

## Where is the Shift calls switch?
- On the volunteer's page (signed link and My Account's section): "Shift calls", a switch "Ask me when a shift still needs volunteers" and the channel among the programme's (a list only with two or more); action `calls` posted to `volunteer.php`, saved by `pg_volunteer_calls_save()`. Shown only when the programme has a call channel. Off stops calls, never the reminder.

## What was not verified?
- No database, SMS provider or Meta account in the cloud session: no pass was run against real rows and nothing was sent.

Related: docs/memory/core/volunteer-board.md, docs/memory/core/volunteer-shift-rules.md, docs/memory/core/volunteer-signup.md, docs/memory/commerce/walk-in-queue-notices.md
