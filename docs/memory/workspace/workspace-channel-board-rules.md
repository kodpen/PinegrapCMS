---
name: workspace-channel-board-rules
description: The rules of a channel's board on the server (BM4): the four ready rules, which flows are the board's rules, a rule made, published, resumed and paused, the gate, the two panel actions
---

## Where is it?
- `includes/workspace/channel_board_rules.php`; tests `tests/workspace_channel_board_rules_test.php`. The drawer that draws it is step BM5 of `plans/workspace-channel-board.md`.
- Nothing is stored: no table, column or right. A rule is a flow.

## Which flows are the board's rules?
- A flow whose event start waits for `workspace.task.completed`, `workspace.task.status_changed` or `workspace.task.overdue` (`ws_board_rule_events()`) narrowed to the channel, or narrowed to none in a flow whose home is the channel (`ws_board_rule_start()`).
- Read from the live version's graph, the draft only for a flow never published; drafts, live and paused flows, never binned (`ws_board_rule_flows()`, through `ws_flow_access()`).

## What are the ready rules?
- `ws_board_rule_templates()`: done_post (a done task written in the channel), overdue_remind (an overdue task written in the channel, "Remind its people in the channel"), waiting_approval (a move to Waiting asks for approval; asks who approves), done_next_task (a done task makes the next task in the channel, nobody on it; asks its title).
- The overdue rule posts in the channel: a notify box takes fixed people only, and the task's people already get the overdue reminder (`workspace-task-reminders.md`).
- A ready rule's flow has the start box id `board_<key>`; `ws_board_rule_key_of()` reads it back only while the start still waits for that rule's event and status. Two flows of one rule: the live one is the rule, the others are listed as flows of their own.

## What does turning a rule on or off do?
- `ws_board_rule_set()`: on makes a ready rule's flow (`ws_flow_save()` with the answer, home the channel, name "Board: …") and publishes it (`ws_flow_publish()`); a paused rule is resumed on its version (`ws_flow_resume()`); a made rule's draft is published. Another flow of the channel is only resumed; one never published is refused ("Open this flow in Flows to publish it.").
- Off pauses (`ws_flow_pause()`), never deletes. The flows' own checks decide (owner only, rights at publish), and their refusals come back as they are.

## Who may?
- `ws_board_rules_gate()`: `pg_flows_enabled()`, a public or private channel with a board that the person reads, and a flow builder on the staff (a role-3 member's flow has no home channel). Without it there is no "Automations" button.

## Which panel actions?
- `ws_channel_board_rules` (channel_id) answers `ready`, `flows`, `on`; `ws_channel_board_rule_set` (channel_id, key or flow_id, on, answer) sets one and answers the same list. A row: flow_id, name, status, on, owner, edit, when, column (the status column its line goes under: done for a completed task, the status of a move, '' for overdue).
