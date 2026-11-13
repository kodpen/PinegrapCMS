---
name: campaign-profile-flow-map
description: An e-mail campaign profile's action, delay and send time laid against the flow boxes that would do their work (MA9a, #9077): which action has a start box, what the Wait box covers
---

## Which start box does each action become?
- A profile acts in `create_auto_email_campaigns()` (`includes/fn/mail.php`) at its action; a flow starts from the row `pg_event_record()` writes, taken later by `ws_events_process()`. The event start box narrows to a form (`page_id`), a contact group or an order status; any other item is a Condition on `{{trigger.record_id}}`.
- With a start: `custom_form_submitted` → `form.submitted` on that form; `order_completed` → `order.created`; `order_shipped`, `order_delivered` → `order.shipped`, `order.delivered`; `contact_group_joined` → `contact_group.joined` on that group; `calendar_event_reserved` → `calendar_event.reserved` and a Condition record_id = the event.
- Also: `subscription_renewed`, `_failed`, `_cancelled` → `subscription.*`; `booking_confirmed`, `_rescheduled`, `_cancelled` → `booking.*`; `credit_issued` → `credit.issued`.
- No start today: `order_abandoned` (the scan in `job.php`), `order_review_request` (`pg_review_request_start()`), `product_ordered` (one call per order line; `order.created` names no products), `booking_reminder`, `credit_expiring`, `subscription_price_changed` (called where their moment is worked out; no event).
- `email_campaign_sent` (the followed profile) has no start: it becomes boxes after its leader's e-mail box ([[campaign-profile-flow-switch]]).

## What do the delay and the send time become?
- Profile: `schedule_length` × `schedule_unit` (days or hours, up to 9 digits) after the action, then moved to `schedule_time` on that day or the next (00:00:00 is any time; a chosen midnight is stored 00:01). A calendar reservation may count before or after the event's start. A moment already past makes no campaign; the campaign job sends at `start_time`.
- Flow: the Wait box (`delay`, `workspace/workspace-flows-delay-box.md`) waits from a minute up to `WS_FLOW_DELAY_MAX`, or until a date less a time; it has no time of day, and a date already past goes on at once.
- Covered: a length within `WS_FLOW_DELAY_MAX` with "any time", after the action; length 0 needs no Wait box. Not covered: a send time, a longer wait, before or after a calendar event's start (`calendar_event.reserved` carries no start; Read a record has no calendar event).
- Purpose, consent and the e-mail itself: [[campaign-profile-flow-email]].
