---
name: messages-area-sends-calendar
description: Communication › Sends › Calendar (MA13 #9575): what waits to be sent by day and hour, its four sources, rights, the same-group-same-hour mark, the Work Calendar's grid reused, read only
module: messages_calendar
---

## Where is it?
- On the Sends tab: "List | Calendar" (Liste | Takvim) at the toolbar's start; `messages_sends.php?view=calendar`, `cal=month` for the month (week by default), `date=Y-m-d` for the days shown. Previous, Today, Next and the dates shown beside them.
- Code: `includes/fn/messages_calendar.php`; the screen keeps the Sends tab's gate (`validate_email_access()`).

## What does it show?
- Manual e-mail campaigns with status `ready` and a `start_time` in the days (a role 3 user's own); edit screen `edit_email_campaign.php`.
- SMS campaigns with status `ready` and `start_at` in the days, while `pg_sms_campaigns_ready()` (role 3: own); `edit_sms_campaign.php`.
- Live flows the person reaches (`ws_flows_list()`, as the Automatic sends tab) that start at a time and hold a send box: from their anchor's `next_run_at` and rule (`ws_scheduled_actions`, anchor hidden or converted), each following run through `ws_scheduled_next_run()`, at most 100 a flow (`PG_MESSAGES_CALENDAR_FLOW_CAP`); `workspace_flows.php?flow=`.
- E-mails under Send later (`ws_mail_items.send_state = later`, `send_at`) in conversations of channels the person reads (`ws_mail_list_channels()`); `messages_mail.php?conversation=`.
- Nothing older than an hour ago is read. It writes nothing: no table, no column (owner's step line).

## How is a clash marked?
- `pg_messages_calendar_clashes()` (pure): two sends whose included contact groups share one, in the same clock hour of the same day, get an amber ring and a warning mark, the group named in the title; a notice above the grid counts them.
- Only campaigns carry groups (`contact_groups_*_campaigns_xref`, type included). Flows and Send later mails go to addresses, so they never clash.

## How is it drawn?
- Server-side with the Work Calendar's classes (`ws-cal`, `ws-cal-head`, `ws-cal-grid`, `ws-cal-day`, `ws-cal-num`, `ws-cal-event`), wrapped in `#ws-sendcal` so the `--ws-*` tokens apply. A week lists each day's sends under their hour; a month lists four and "N more" leading to that week. Chips take the kind's tone: e-mail campaign blue, SMS amber, automatic send violet, Send later teal; a key under the grid.
- Under 768 px one column of the days that hold a send (and today), each named in full; the Work Calendar's phone bars are undone for its chips.

Test: `tests/messages_calendar_test.php`.

Related: docs/memory/platform/messages-area-sends.md, docs/memory/ui/messages-area-look.md
