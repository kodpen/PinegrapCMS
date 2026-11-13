---
name: workspace-flows-contact-group-box
description: Flow box Add to or take out of a contact group (contact_group, #9291): settings, whose contact, hand groups only, no consent written, a flow not started again by its own add
---

## What does it do?
- A step box, kind `contact_group` (`includes/workspace/flow_contact_group.php`), label "Add to or take out of a contact group". Settings: `group_id`, `way` (`add` or `remove`), `contact` (an id or one value such as `{{trigger.contact_id}}`; empty is the contact of the run's record).
- Outputs `contact_id` and `changed` (1, or 0 when the contact was already as asked). A contact not found or out of the owner's reach fails the box.
- Added because the quick setup's drawn step "Kişiyi gruba ekle" had no box (`workspace-flows-quick-setup-rules.md`).

## Whose contact is it?
- Empty contact: the run's record as the e-mail box reads its person (`ws_flow_mail_person_source()`): a contact run, an order's or a submitted form's `contact_id`, a received e-mail's conversation. A booking has no contact: the box fails.
- The contact is read with the owner's rights (`ws_step_record_get_read($owner, 'contact', …)`), so a role-3 owner reaches only contacts of their groups.

## Which groups?
- Groups filled by hand only (`pg_audience_hand_sql()`), every one from the manager role up, below it the groups given to the account (`ws_step_contact_group_choices()`, sent to the canvas as `steps.groups`; none, and the palette has no such box). A group switched to rules after publish is refused as the box runs (`pg_audience_group_is_dynamic()`), as every hand door is (`core/audiences-doors.md`).
- Owner needs the contacts right (`ws_change_allowed($viewer, 'contact')`).

## What does it never write?
- Only the `contacts_contact_groups_xref` row. Adding never sets `contacts.opt_in` nor an `opt_in` row of the group: a contact who never agreed to marketing stays so (architect, 2026-11-13).

## Why does a flow not start itself again?
- A change is announced through `pg_announce_contact_group_members()` like every door, with the flow's id as its fifth argument; each `contact_group.joined` payload then carries `_flow`. `ws_events_start()` takes `_flow` out and skips the flow it names (`ws_events_own_group_add()`, pure). Other flows and campaign profiles still hear the join.
- Before, nothing guarded it: a flow on "joined group X" adding to X would have run again until the chain limit.

## Where else is it named?
- Tier: irreversible (`ws_flow_box_tiers()`), as a join can send a "joined a group" campaign mail. Not in `ws_flow_member_kinds()`. Records group of the quick setup and the palette. Pinegrap AI hint in `ws_flow_ai_catalogue_hints()`. Its contact is held from readers without edit.
- Tests: `tests/workspace_flow_contact_group_test.php`.
