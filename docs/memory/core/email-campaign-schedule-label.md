---
name: email-campaign-schedule-label
description: The schedule label on My Campaign Profiles built from two whole keys, the send time as {var:4} at the end
module: email_campaign_schedule
---

## How is the schedule label built?
- `pg_email_campaign_profile_schedule_label($length, $unit, $period, $base, $time_label)` (`includes/fn/email_campaign_schedule.php`) returns plain text; `view_email_campaign_profiles.php` loads the module and prints the label through `h()`.
- Pieces without edge spaces: {var:1} `immediately` for 0, else the number, one space and `day`/`days`/`hour`/`hours`; {var:2} `lang($period)`; {var:3} `from action` or `from calendar event start time`.
- Keys: `Schedule: {var:1} {var:2} {var:3}` = "Zamanlama: {var:3} {var:1} {var:2}", and with a time `Schedule: {var:1} {var:2} {var:3}, at {var:4}` = "…, saat {var:4}". The caller passes '' for 00:00:00, else `date(pg_time_format_code(), …)`.

## Why (#3610)?
- The time was glued to {var:3} in parentheses and the old key `Schedule: {var:1}{var:2}{var:3}` carried the spaces inside the pieces, so the Turkish order gave "eylemden (10:00)1 gün sonra". Tests: `tests/email_campaign_wording_test.php`.
