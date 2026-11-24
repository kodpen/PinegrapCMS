---
name: workspace-flows-leave
description: Leaving a flow (FE14, flow_leave.php): leave rules in the graph, events offered, a run's subject from its event, the hook by idx_subject, status left, the name window's part
---

## What does the graph keep?
- `leave`: `[{event}]`, an event's name once, in order (`ws_flow_leave_normalize()`, in `ws_flow_graph_normalize()`); `ws_flow_graph_empty()` has it, `ws_flow_graph_present()` passes it.
- More than 10 (`WS_FLOW_MAX_LEAVE`) is a `too_many` problem in `ws_flow_graph_check()`; an event not offered is a `bad_config` problem in `ws_flow_problems()` (`ws_flow_leave_problems()`).
- Code: `includes/workspace/flow_leave.php`. No schema: published with a version; a run keeps its version's rules.

## Which events are offered?
- `ws_flow_leave_offered()` (pure) over `ws_events_catalog()`: events whose `record` is a person or a record. Left out: record '' or `channel`, the on-demand events (`pg_event_workspace_on_demand()`, kept only while a start names them) and the workspace's own (`ws_events_own()`, kept only while something waits).
- The canvas gets `FL.leave` (`ws_flow_leave_js()`: `[event, label]` the person may wait for) and `FL.limits.leave`.
- With the Workspace off, a site event is kept only while a live flow's start names it (`pg_event_record()`), so a leave rule alone does not keep it.

## What is a run about?
- A run an event started (`ws_flow_anchor_start()`) gets `subject_type`/`subject_id` from `ws_flow_leave_run_subject()`: the event's person's contact first (the record's own when a contact, the payload's `contact_id`, else an order's or a form's `contact_id`), else its record (`ws_events_record_of()`). A run started by hand for a customer keeps that contact. Chain, schedule and join runs have none and never leave.
- `ws_flow_mail_person_source()` reads the subject contact only for a run with no start event, so an event run's e-mail address still comes from its record.

## How does a run leave?
- `ws_events_process()` calls `ws_flow_leave($event, $payload)` after `ws_flow_wake()`. It reads the open runs about each thing the event names (`ws_flow_leave_subjects()`: the contact, then the record) by `idx_subject`, at most 200 each, and ends those whose version has a rule for the event (`ws_flow_leave_rule()`).
- An event a box caused (payload `_depth` > 0) ends no run, so a run never ends itself.
- `ws_flow_run_leave()`: one conditional UPDATE to `left` with `finished_at`; open boxes skipped with "The run left the flow: <event's label>." (a task stays, an approval request stays open, `ws_flow_leave_result()`). A converted action's run is written back as skipped. No inbox line and no webhook: the owner is not told as for a failure.

## Where is left shown?
- `ws_flow_run_statuses()` has `left` ("Left the flow"); the canvas's `RUN_STATES` and the flag's `FLAG_STATES` (`bi-box-arrow-right`, the secondary tone) cover the runs list, run view and run card. Swept at 30 days as other ended runs. Retry and skip are not offered on a left run.
- API: run `status` `left`, the runs list's `status=left` filter, the draft update's `leave` part (`ws_api_flow_input()`; a create takes none, for the MCP tool's size budget), `WorkspaceFlowGraph.leave`, `left` in `ws_flow_counts()` (runs left in the days).

## How is it set on the canvas?
- "Leaving the flow" in the name's window (`leaveField()` in `workspace_flow.js`, after the home channel): each rule's event with × (Remove the rule), then a list "Choose what happens" and "Add a rule" while the draft may change and under the limit; each change is one undo step.
- Under it "Runs that left this way in the last 30 days: N" from `left_30` of `ws_flow_detail()` (`ws_flow_left_count()`, for whoever may change the flow).

## Where is it tested?
- `tests/workspace_flow_leave_test.php`.

Related: docs/memory/workspace/workspace-flows-runner.md, docs/memory/workspace/workspace-flows-name-window.md, docs/memory/plans/flows-editor-next-screens-3.md
