---
name: calendar-shared-form
description: The calendar create and edit screens share includes/forms/calendar_form.php: what it holds and what stays in the screens
---

## Where is the calendar form?
- `includes/forms/calendar_form.php` (gate `PG_FUNCTIONS_DIR`, PR #2690). `add_calendar.php` and `edit_calendar.php` stay at the root as thin wrappers and `require_once` it. The calendar event pair is a separate form (`calendar-event-shared-form.md`).
- `pg_calendar_form_card($liveform)` prints the Options card: the calendar name (maxlength 100, `required` in the browser on both screens).

## What does a save share?
- `pg_calendar_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required ("Name is required."), then not used by another calendar (edit skips its own row with `id != ?`). A required error skips the name lookup, as before; the screen redirects back on any error.

## What stays in the screens?
- Create: the role-3 refusal, the INSERT, the notice on the calendar list.
- Edit: the not-found guard, `validate_calendar_access()`, the event count (delete is refused while the calendar holds events), the record load, delete with its four xref tables, the UPDATE, the hidden id, the notice on `calendars.php`.
- Tests: `tests/calendar_form_test.php`; `db_q_regions_menus_calendars_test.php` (edit screen, form) and `db_q_small_functions_test.php` (create screen) pin the queries.

## Why does a refused name stay in the box?
- Edit Calendar assigns the stored name only while `$liveform->prefill()` (no post came back), so a name refused as already in use stays next to its error instead of the old name.
