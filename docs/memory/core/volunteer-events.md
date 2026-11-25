---
name: volunteer-events
description: Volunteer shift events (VS8): applied, approved, shift signed up and unfilled, checked in, hours approved; where each is announced, after the shift lock; the catalogues
module: volunteer_events
---

## Where are they built?
- `includes/fn/volunteer_events.php` (in `functions.php`): the pure `pg_volunteer_event_payload($event, $row)`, `pg_volunteer_event_time()` (unix or the site's wall-clock DATETIME as ISO 8601 UTC), `pg_volunteer_shift_needed()`; `pg_volunteer_announce()` reads the profile or sign-up back and announces; `pg_volunteer_announce_unfilled()`. Tests: `tests/volunteer_events_test.php`. Built in step VS8 (`volunteer-shifts.md`).
- Fields and samples: `api_webhook_event_payloads_site()` (`$volunteer`, `$place`); the builder fixture in `tests/webhook_event_payloads_test.php`. No event carries a document, link or message.

## When is each announced?
- `volunteer.applied`: `pg_volunteer_application_record()` after a new `volunteers` row (a second entry of the contact announces nothing).
- `volunteer.approved`: `pg_volunteer_move()` from applied to active (Approve and Approve selected).
- `shift.signed_up`: a place becomes signed, `how` sign_up, waitlist (moved up when a place opened) or swap (an offered place taken, at once or by the coordinator's Approve). Collected under `GET_LOCK('pg_volunteer_shift_<id>')` and announced after `RELEASE_LOCK` in `pg_volunteer_self_act()` and `pg_volunteer_swap_decide()`; `pg_volunteer_waitlist_write()` hands its moved rows back by reference.
- `shift.unfilled`: the first call of a shift in `pg_volunteer_shift_call_send()` (job pass or Call volunteers), when the shift's `called` list was empty; `needed` places free, `asked` volunteers. A shift whose call asks nobody (no channel, outside 09-20, nobody fits) is not announced.
- `volunteer.checked_in`: `pg_volunteer_check_write()` with step check_in (QR scan, `checked_by` null, or the coordinator's Check in).
- `volunteer.hours_approved`: each row `pg_volunteer_hours_approve()` writes, of a shift or outside one (`shift_id` null).

## How does the Workspace see them?
- All six in `pg_event_workspace_events()`; flow start events in `ws_events_catalog()` once `pg_volunteer_ready()`, right `contacts`, record the volunteer's contact (`shift.unfilled` none); outputs in `ws_flow_event_outputs()`; area Volunteers in `ws_flow_quick_areas()` and `api_app_event_area()`; line in `tests/workspace_flows_parity_test.php`.

Related: docs/memory/api/api-volunteers.md, docs/memory/api/api-webhook-events.md, docs/memory/core/volunteer-signup.md, docs/memory/core/volunteer-hours.md
