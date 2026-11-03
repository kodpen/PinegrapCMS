---
name: calendar-event-exception-endpoint
description: update_calendar_event_exception.php (Remove / Unremove a repeating event's instance): parameter check, instance range, no second row
module: calendar
---

## What does the endpoint accept?
- `action` create or delete, `calendar_event_id` (from 1) and `recurrence_number` (from 0), each one string of digits: `pg_calendar_event_exception_request($_GET)` in `includes/fn/calendar.php`, null otherwise. Null, an unknown event or an instance the event does not have (`pg_calendar_event_exception_number_ok()`: the event repeats and the number is 0 to its stored repeat count) answer `output_error('Record not found.')`.
- Before (#5576) a link without `calendar_event_id` logged "Undefined array key" and returned a blank page, `action=bogus` a blank page, and `recurrence_number=99` on a 3-repeat event was stored.

## Can the same instance be removed twice?
- No: `create` looks for the pair first and only redirects when it exists. The table has no unique key on it (a schema change would be an owner decision), so a reload or a double click wrote a second row.

Tests: `tests/calendar_event_exception_test.php`.
