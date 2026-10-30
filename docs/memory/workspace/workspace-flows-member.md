---
name: workspace-flows-member
description: A role-3 member's own flows (#3345 narrow, R1 #3397, flow_member.php): who builds, the kinds of box allowed, no home channel or shares, caps 10 flows and 20 starts an hour, kinds re-checked at run
---

## Who builds flows now?
- `ws_flow_builder_kind()` (pure): `staff` (team member, role 0-2, as `ws_can_schedule()`), `member` (role 3), '' (outside the team). `ws_flow_builder()` adds `ws_scheduled_ready()`; `ws_flow_member()` is the role-3 case.
- `ws_flow_save_check()` lets every builder save ("Only people in the team can build flows." otherwise); `ws_flow_access()` gives a role-3 owner `edit` on their own flow, so publish, pause and delete work through `ws_flow_share_manager()` as for staff. A share at edit to a role-3 member still gives `run` (`ws_flow_share_gives()`).
- Staff behaviour is unchanged. Making a flow from a table (`ws_wtable_flow_create()`), templates, the draft card and the canvas entry stay staff's until R2 (plan `workspace-flows.md`, slice 9).

## Which boxes may a role-3 flow hold?
- `ws_flow_member_kinds()`: starts manual, schedule, event; end, task, approval, delay, condition, constants, value; post, notify, task_open, report, task_digest; row_add, row_set; change (R3, only as a proposal its owner approves: `workspace-flows-member-change.md`).
- Refused (`ws_flow_member_node_refusal()`): webhook, web_check, mcp, email, lead, checkin, start_action, start_flow, for_each and its end, join and row starts; a report with e-mail addresses (`to`): it goes to a channel; a change box without `channel_id` (where it asks).
- Reach inside an allowed box is the existing check with the owner's rights: `ws_step_check()` at publish and in `ws_step_run()` (a channel they post in, counts their rights read, tasks to people they may give work to, tables they edit through `ws_wtable_flow_check()`), `ws_flow_condition_check()`, `ws_flow_anchor_check()` (an event their rights see; own events again per event through `ws_events_creator_sees()`).

## Where is it checked?
- Save and publish: `ws_flow_member_flow_refusal()` in `ws_flow_save_check()` and `ws_flow_publish_check()` (field `channel_id` for a home channel, `graph` for a box, named "The box …: …"). The panel and the external API both save through `ws_flow_save()`.
- Run: `ws_flow_step_run()` asks `ws_flow_member_box_refusal()` with the owner (`actor_id`) as they stand now, so a flow built on the staff stops at a refused box once its owner is role 3; the box fails as any refused box. Box try (`ws_flow_box_try()`) asks it too. Every run path accepts a role-3 owner through `ws_flow_builder($owner)`; `ws_scheduled_execute()` accepts one only for a flow's anchor row.
- Anchor start (`ws_flow_anchor_start()`): a role-3 owner's event box is checked again with `ws_scheduled_rule_input()` (a right lost meanwhile starts nothing).

## Owner-only?
- No home channel (save and publish refuse `channel_id` > 0), no shares (`ws_flow_share_set_check()` refuses the role-3 owner), and `ws_flow_run_start_check()` refuses anybody but the owner (`ws_flow_member_starter_refusal()`), whatever home or share a flow kept from its owner's staff days.

## Which limits?
- `WS_FLOW_MEMBER_MAX_PER_OWNER` 10 flows kept (`ws_flow_max_per_owner()`; staff `WS_FLOW_MAX_PER_OWNER` 200).
- `WS_FLOW_MEMBER_STARTS_HOURLY` 20 starts an hour over all the owner's flows together (`ws_flow_member_starts_check()`, runs by `actor_id`), by hand and by anchor; the per-flow caps of `ws_flow_start_refusal()` hold as well.

## Tests
- `tests/workspace_flows_member_test.php` (pure rules); source checks in `workspace_flow_runs_test.php`, `workspace_flow_steps_test.php`, `workspace_scheduled_flow_window_test.php` name `ws_flow_builder()`.
