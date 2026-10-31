---
name: calendar-event-edit-screen
description: Edit calendar event screen (edit_calendar_event.php): which recurring instance it edits (recurrence_number) and how a missing or array value is read
---

## Which recurring instance does the screen edit?
- `recurrence_number` in the query string. The edit button on a calendar event page adds it (`get_page_content.php`); the form posts it back in a hidden field, and a form error redirects with it.
- It only matters when reservations are separated per instance (`separate_reservations`): the remaining spots shown and saved are that instance's row in `remaining_reservation_spots`. Otherwise instance 0 is used.
- Opened from the calendars list it is not given: the hidden field stays empty and the lookup reads instance 0, as before.

## How is the value read?
- Once, at the top, into `$requested_recurrence_number`: `pg_request_single_values(array('recurrence_number'))` first (before liveform copies `$_REQUEST`), then missing or "" stays "", anything else is `(string) max(0, (int) value)`, the same reading as `get_calendar_event_view_screen_content.php`.
- Do not read `$_GET['recurrence_number']` anywhere else on the screen; `tests/calendar_event_edit_test.php` checks it.

## What did it do before (PR #491)?
- Every open without the parameter wrote two "Undefined array key" warnings (lines 215 and 455), printed on the page where PHP errors are shown (seen on dev 2026-10-16).
- `?recurrence_number[]=x`, or a posted array with separated reservations, answered 500 (`escape()` on an array); a posted array with a form error redirected to `recurrence_number=Array`.
- The page HTML and the saved remaining spots are unchanged for a real number or no number.

## Does a refused save keep the return address?
- Yes: the redirect adds `send_to` through `urlencode()`, so a site event page's address (`/event?id=12&recurrence_number=3`) survives whole and the back arrow (`pg_send_to_url('calendars.php')`) opens that occurrence. Before, the address was cut at its first `&` and the arrow opened the first occurrence. Pinned by `tests/edit_return_address_test.php`.


## When does a save write Remaining Spots?
- The form carries `number_of_remaining_spots_was`, the instance's spots when the screen opened (its row, or the initial spots when it has no row). A save whose Remaining Spots equals it leaves `remaining_reservation_spots` alone.
- A changed value is written (DELETE + INSERT, as before) only when the stored value (`pg_calendar_event_stored_spots()`, same instance number as the write) still equals the opened one. Otherwise nothing is saved: the field says "…changed while the screen was open: it is now N", the was value becomes N and the error redirect keeps `send_to` and `recurrence_number`; saving again writes the typed value.
- Why: a reservation takes spots off relatively (`order_completion.php`, `number_of_remaining_spots - ?`). With 12 left, three reservations while the screen was open, a description save put 12 back and oversold 3 seats.
- The decision is `pg_calendar_event_spots_decision()` in `includes/forms/calendar_event_form.php` (keep / write / stale; a form without the was field writes as before), pinned by `tests/calendar_event_edit_test.php`. Same mechanism as `commerce/gift-card-balance-edit.md`.


Related: `core/request-parameter-arrays.md` (array = not given).
