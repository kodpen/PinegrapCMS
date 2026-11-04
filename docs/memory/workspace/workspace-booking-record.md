---
name: workspace-booking-record
description: Booking as a Workspace record kind (tag <#booking:N>, the agenda's row rule, tag-only, bookings:read) and the four booking.* events of scheduled actions, checked against the creator's agenda
module: booking_agenda
---

## How is a booking a Workspace record (#6629)?
- Kind `booking` in `ws_record_kinds()` (prefixes `randevu`, `rezervasyon`, `booking`, `appointment`): every member may tag one (right '', there is no booking permission column), but `ws_refs_resolve()` opens it only through the agenda's rule, `pg_booking_agenda_user_scope()` + `pg_booking_agenda_scope_allows()` (pure): roles 0-2 every booking, a role-3 staff member their own `booking_staff` row's, anyone else none. As `edit_booking.php` and the API (`pg_booking_agenda_scope_sql()`).
- Chip: "service · customer", the start (`pg_booking_agenda_time()`), the status label, `edit_booking.php?id=N`. `ws_ref_search()` matches name, e-mail, phone, service or the id, never a hold. Neither reads anything before `pg_booking_ready()`.
- Tag-only: no `ws_change_types()` kind, so no drawer, bulk or audit line; a booking moves on the agenda, where `pg_booking_changed()` mails the customer. No customer kind.
- Scope `bookings:read` in `ws_api_search_record_scopes()` and `ws_ai_type_scope()`; listed in the task routes' `refs` and the MCP record resource. `edit_booking.php` draws the Workspace button.

## Which events start a scheduled action?
- `booking.created`, `.confirmed`, `.rescheduled`, `.cancelled` in `ws_events_catalog()` once `pg_booking_ready()`: right `member`, no filter, record booking (`ws_events_record_of()` reads the payload `id`). `pg_booking_changed()` announces them; `pg_event_workspace_events()` keeps them in `ws_events_in`.
- Any member may choose one, so `ws_events_checks_creator()` (own events and `booking.*`) makes `ws_events_start()` and `ws_step_trigger_values_for()` ask `ws_events_creator_sees()`. Its booking branch reads the booking's current `staff_id` against the creator's agenda scope: a role-3 member who is not that staff member never has an action started, nor sees its values.
- Not written into watching channels (`ws_events_watched()`), as a new form is not.
- Each has its line in `workspace_flows_parity_table()`.

## Why no new right?
- A `ws_viewer()` right or a permission column would be a schema and product question; the agenda's own row rule already decides who sees a booking, so the tag is open and the rows are narrowed.

## What is unverified?
- No booking event ran on a dev database; the chip, search and button were not opened in a browser. Pure parts: `tests/watch_test.php`, `tests/booking_agenda_test.php`, the record-kind tests.

Related: docs/memory/workspace/workspace-bookings.md, docs/memory/commerce/booking-messages.md, docs/memory/workspace/workspace-record-kinds.md
