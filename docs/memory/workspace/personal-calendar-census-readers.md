---
name: personal-calendar-census-readers
description: PC1 census (2026-11-14): every reader of the Work Calendar's rows (ws_events) and of Today, which one asks whose row it is, and what each must do with a person's private plan
---

## Which readers of `ws_events` never ask whose row it is?
- `ws_events_between()` (schedule.php) reads every row of a range; its callers filter afterwards, or not at all.
- No filter: Work Calendar team view (`ws_calendar()`, `ws_event_in_view()` with person 0), planning board and capacity (`ws_plan_grid()`), GET /workspace/plan-items without `user_id` (`ws_api_plan_items_list()`).
- No filter either: the "#" reference search (`ws_ref_search()` type `plan`, by title), meeting reminders (reminders.php, by `remind_at`), the days off (`ws_holiday_year()`), booking busy times (`pg_booking_busy_between()`, widgets_booking.php), the outgoing subscription's read (calendar_feed.php, filtered after by `ws_event_applies()`).
- Filtered by `ws_event_applies()` or its SQL twin (company, department or named): Today's calendar card (`ws_home_calendar()`), the signals the dashboard and assistants read (`ws_dashboard_signals()`, briefing.php), a staff member's busy times (`pg_booking_agenda_ws_busy()`), the my-day route (api_day.php), dashboard widget 29 and the new dashboard's week card.
- By id or contact: the reference cards (refs.php), the plan-item drawer and GET /plan-items/{id}, `meeting_soon` lines (notify.php), customer history visits, erasure and merge (`pg_contact_holders()`).

## So where may a private plan be kept?
- Never in `ws_events`: every reader above would show it, count it as busy, as a day off or as a meeting. A plan has its own table and one gate, `ws_personal_plan_can_see()` (name: `personal-calendar-census-words.md`), read by every reader of that table.

## Which readers draw a person's plans, and which leave them out?
- Draw (owner, and the people it is shared with): My calendar (PC2's own view of `ws_calendar()`), Today's "Calendar · today" card (`ws_home_calendar()`, beside the own iCal layer and bookings), the plan's reminder line.
- Proposed to leave out: the team view and any person filter but one's own, the planning board and capacity (the plan says a plan takes no capacity), days off, booking busy times, the outgoing iCal subscription and the Google/Outlook writer (outside the plan), dashboard widget 29 and the week card, the dashboard signals and the my-day route (PC8 decides), the "#" reference search.

## What else on Today counts things?
- The count line (`ws_home_lead()`) counts unread talk, approvals and tasks due; a plan is not added (proposed). "Today and overdue" lists tasks only. "Waiting for you" lists approvals, receipts and talk; a plan's reminder arrives as an inbox line, not a row there.

## Which other places could leak a private plan?
- The Site Log (`log_activity()`): plan items write none; a plan writes none either, or only without its title.
- `ws_event()` / webhooks / flows: plan items emit none; a plan emits none (PC8 adds its own scope, never a channel's).
- The inbox and push (`ws_inbox`, `ws_inbox_row_visible()`), the API sync of the inbox (`changed_since`): a reminder line is read back through the gate, and `ws_inbox_reach_lost()` is called when a share is taken away.
- `record_revisions`, `audit_log` before/after: the plan's title and note are personal text; an audit line names the plan by id only.
- Search and the assistants: no index; Pinegrap AI and channel agents get no tool on the table (plan rule; PC8).

## Tests to guard it?
- PC3 adds a guard test that every file reading the plan table calls the gate, as `tests/workspace_record_kinds_test.php` does for record kinds.
