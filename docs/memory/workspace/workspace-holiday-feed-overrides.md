---
name: workspace-holiday-feed-overrides
description: Holiday calendar addresses (ws_ical_events()): a moved occurrence gets its own uid so it never replaces its yearly day; cancelled days are left out
---

## What went wrong with a moved year?
- Google and Outlook export a yearly day with one year moved as two VEVENTs with the same UID: the master (RRULE:FREQ=YEARLY) and an override with RECURRENCE-ID. `ws_holiday_feed_sync()` keys the read events and the `ws_events` rows (`feed_uid`) by uid, so the later block won: the moved year replaced the yearly row and the day off vanished in every other year (or the moved year was lost).

## What does the reader do now?
- `ws_ical_events()` gives an override the uid `<UID>|<RECURRENCE-ID digits>` (clipped to fit `feed_uid` VARCHAR(191)); the master keeps the bare UID and stays yearly. A row written earlier under the bare UID with the override's date is corrected on the next read.
- An event with `STATUS:CANCELLED` is no day off and is left out, as the personal reader (`workspace-personal-ical.md`) already did.

## What is still not modelled?
- A yearly row cannot skip one year: in the moved year the original day still counts beside the moved one, and EXDATE is not read. An admin can leave a day out per row only (`ws_holiday_feed_day_skip()`), which skips every year of a yearly row.
- Tests: `tests/workspace_holiday_feed_test.php`.
