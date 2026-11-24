---
name: feedback-sends
description: Satisfaction surveys queued (FD3): the events that queue one, the row and its token hash, the cancels, booking.completed, workspace.mail.closed, ticket.checked_in in flows
module: feedback_sends
---

## Which events queue a survey?
- `pg_announce()` hands every event to `pg_feedback_heard()` (`includes/fn/feedback_sends.php`, loaded everywhere). `pg_feedback_event_record()`: `booking.completed` (booking), `ticket.checked_in` (ticket), `course.completed` (course id), `form.submitted` (submission), `order.delivered` (order), `workspace.mail.closed` (conversation), `queue.served` (touchpoint `queue`, record type `queue`, the walk-in number).
- `custom_form.php` queues `form.submitted` with `pg_announce()` without its listeners, so it calls `pg_feedback_heard()` itself.
- Each switched-on, not archived survey of the touchpoint whose filter takes the record (`pg_feedback_filter_takes()`: service, event, course, form page, mail box, queue place; 0 any) gets a row. A failure is logged, never thrown to the announcing code.

## What is stored at the queue?
- `pg_feedback_queue_row()` (pure): the recipient by the survey's channel (address; number in `pg_sms_number()` form), `person_hash`, `subject_id` (service, calendar event, course, form page, mail box), `staff_user_id` (`booking_staff.user_id`), `due_at` = now + delay moved into the sending hours.
- `pg_feedback_queue()` inserts, then in the same call `pg_feedback_set_due()` writes `token_hash` of the `answer` token issued at `due_at`: the sender rebuilds the link from id and `due_at`; it holds 30 days from the due time. No key (`ENCRYPTION_KEY`), no row.
- A person with a row of the same survey still queued gets no second one (two tickets, two addresses of one order).

## What cancels a waiting survey at once?
- `booking.cancelled` (in `pg_feedback_heard()`), an order cancelled (`process_order_cancellation()`) or fully refunded (`pg_return_refund()`), beside `pg_review_request_cancel()`; a conversation opened again by hand (`ws_mail_conversation_set()`): `pg_feedback_cancel_record()` sets queued rows to 3. The send looks again too: [[feedback-send-pass]].

## Which events did FD3 add?
- `booking.completed`: `pg_booking_status_event()` and the API's `api_booking_move_event()` give `completed` for a move to `done`; `pg_booking_changed()` announces it and mails nothing (`commerce/booking-messages.md`).
- `booking.completed` and `ticket.checked_in` are flow starts (`pg_event_workspace_events()`, `ws_events_catalog()`, outputs, quick setup's Reservations area); the ticket's record is its calendar event (calendars right).
- `workspace.mail.closed`: from `ws_mail_conversation_set()` when a change closes a conversation (`ws_mail_conversation_closed_event()`, pure); webhook only, ids, `assignee_id`, `closed_by` (`workspace/mail-status.md`).

## How does a walk-in queue number become a survey? (WQ8b)
- `queue.served` from `pg_waiting_finish()` (Finish on the desk). Facts from `waiting_tickets`: email, phone, contact_id, `place_id` as subject, `staff_user_id` (who called it), state, `cleared_at`; nothing before `pg_waiting_ready()`.
- Starting card "After a queue visit": CSAT, 2 hours, filter `place`. Before the send the number must still be `served` with `cleared_at` 0: a keep period shorter than the delay cancels it. A low score goes to the calling staff user (`pg_feedback_owner()`), else the fallback.

Test: `tests/feedback_sends_test.php`.

Related: docs/memory/platform/feedback-send-pass.md, docs/memory/platform/feedback-store.md, docs/memory/platform/feedback-rules.md, docs/memory/platform/feedback-loop.md
