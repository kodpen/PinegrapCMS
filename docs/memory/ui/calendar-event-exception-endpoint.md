---
name: calendar-event-exception-endpoint
description: update_calendar_event_exception.php (Remove / Unremove a repeating event's instance): parameter check, instance range, no second row; the panel month box that holds the hidden "Removed:" label
module: calendar
---

## What does the endpoint accept?
- `action` create or delete, `calendar_event_id` (from 1) and `recurrence_number` (from 0), each one string of digits: `pg_calendar_event_exception_request($_GET)` in `includes/fn/calendar.php`, null otherwise. Null, an unknown event or an instance the event does not have (`pg_calendar_event_exception_number_ok()`: the event repeats and the number is 0 to its stored repeat count) answer `output_error('Record not found.')`.
- Before (#5576) a link without `calendar_event_id` logged "Undefined array key" and returned a blank page, `action=bogus` a blank page, and `recurrence_number=99` on a 3-repeat event was stored.

## Can the same instance be removed twice?
- No: `create` looks for the pair first and only redirects when it exists. The table has no unique key on it (a schema change would be an owner decision), so a reload or a double click wrote a second row.

## Why is the panel's month box positioned?
- A removed instance is drawn struck through after a `.visually-hidden` "Removed: " (`get_calendar()`, monthly and weekly). That label is `position: absolute`, and the month scrolls sideways inside `div.monthly` (overflow auto) on a phone.
- With the box static the label's containing block was the outer card, so it stood at its place in the wide grid outside the box: on a 390 px screen the page was 826 px wide (#9035). The panel's box now has `position-relative`; the site's markup is unchanged (`tests/calendar_panel_view_test.php`).

Tests: `tests/calendar_event_exception_test.php`.
