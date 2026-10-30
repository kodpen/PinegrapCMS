---
name: workspace-flows-member-change
description: The change box of a role-3 member's flow (R3 #3438, decided in #3345): proposes its change on an approval request to its owner, writes only once approved, fails when refused or expired
---

## When does a change box propose?
- `ws_flow_change_asks($builder)` (pure): true for `member`, asked with the owner as they stand now, so a staff-built flow whose owner is role 3 proposes too, never writes directly. Staff change boxes write as before.

## Which settings and checks?
- Config gains `channel_id` and `closes_in` (as the approval box); `ws_step_action()` leaves them out of the scheduled step. `ws_step_check()` adds `ws_step_change_ask_check()` for a member: the channel and `ws_approval_input()` with the owner as only approver.

## How does it ask and wait?
- Run (`ws_step_run()`): `ws_step_change_ask()` makes an approval request in that channel (title "Approve the change: …", text `ws_step_change_ask_text()`: flow, owner, starter, the change sentence), notifies the owner, writes nothing; `ws_flow_approval_wait(…, 'change')` waits on `approval:<id>`, the F8c propose-and-wait path. No new table or column.

## What happens once it is decided?
- Approved: `ws_flow_approval_recheck()` → `ws_flow_approved_ready()`; the claim runs `ws_step_change_approved_write()`: `began:change` from `approved:<id>` only, `ws_step_change_reach_check()` with the owner now (a lost right fails the box) and `ws_step_starter_refusal()`, then `ws_scheduled_do()` → `ws_scheduled_change()`. Rejected, expired, withdrawn, gone: `ws_flow_change_asked_settlement()` fails the box (on its failed arrow where one leaves), "The change was not made: …".
- `ws_step_starter_refusal()` defaults to `ws_step_change_reach_check()` (the change alone), so a staff flow's role-3 starter is not asked for a channel.
- Box try says "Asks you to approve this change in #… and writes nothing until you do: …". The canvas field for the channel comes with R2.

## Tests
- tests/workspace_flows_member_test.php (`…_change_box_*`), tests/workspace_flow_steps_test.php (`…_change_proposed_by_a_member_owner`, `…_change_proposal_settlement`, `…_change_ask_text`).
