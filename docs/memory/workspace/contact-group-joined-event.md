---
name: contact-group-joined-event
description: Flow event contact_group.joined: one ws_events_in row per contact added to a group, from pg_announce_contact_group_members(); payload, group filter kept as status, outputs
---

## What starts it, and how often?
- `pg_announce_contact_group_members()` (includes/fn/events.php), the seam every door and the recompute pass through: after the `contact_group.members_changed` announce it calls `pg_event_record('contact_group.joined', …)` once per added contact (`pg_contact_group_joined_events()`, pure). A hand edit, a form, a product sale, the API and a rule pass behave alike; the first pass after a switch to dynamic fires for every matching contact.
- A pass of thousands keeps thousands of rows; the existing event run (`ws_events_process()`) drains them in its batches. Nothing new is scheduled.
- Owner decision #6599/#6625 (`workflow/decisions-made-product-27.md`): once per contact, never one run per change.
- Added in #6651.
- A confirmed double opt-in (`pg_fs_confirm()`) announces the contact when its membership insert wrote a row (`pg_fs_confirm_joined()`, pure); a second press and a dynamic group announce nothing. Before, a confirmed sign-up never started a "joined a group" profile or flow.
- Open gap: the form door in `custom_form.php` (its group loop) still inserts without announcing.

## What does it carry?
- Payload `id` (the contact, as every contact event names it), `contact_id`, `group_id`, `group_name`. `ws_events_record_of()` maps it to `contact`; `ws_events_watched()` leaves it out (no line in a watching channel).
- Flow outputs (`ws_flow_event_outputs()`): `contact_id`, `group_id`, `group_name`, beside `event` and `record_id`.
- Webhooks never see it: it goes through `pg_event_record()` only, not `pg_announce()`; `contact_group.members_changed` stays the webhook event, unchanged.

## How is it narrowed to one group?
- Catalogue entry in `ws_events_catalog()`: record `contact`, right `contacts`, filter `group`, not shop-gated.
- The rule keeps the group's id as text in `status`, because the flow's event box (`eventFields()` in workspace_flow.js) writes every list filter (`info.options`) there; no JS change. `ws_events_filter_value()` lays `status` against `group_id` for this event and against `status` for every other.
- The list comes from `ws_events_js_config()` (only for someone with the right): `ws_events_contact_groups()`, up to 300 by name, a dynamic group labelled "{name} (filled by rules)" (`ws_events_contact_group_labels()`, pure).
- `ws_scheduled_rule_input()` checks the group exists outside `$filter_values` (the parity test calls those functions without a database); its rule text reads "When a contact joins the contact group {name}".

## Tests?
- `tests/audiences_joined_event_test.php`; the parity table has the event and the `group` filter.
