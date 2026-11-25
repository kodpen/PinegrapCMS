---
name: workspace-flows-trial
description: A flow's draft tried from start to end without running it (ws_flow_trial(), #11100): record, walk, each box as its try, reads, Wait, chosen outcomes, rights, panel action, API route
---

## What does it do?
- `ws_flow_trial($viewer, $flow, $data)` in `includes/workspace/flow_trial.php` walks the **draft** from its start box as a run walks it (`ws_flow_walk()` over a state kept in memory) and answers every step: `record` (type, id or null), `steps` (node_id, kind, label, name, status done or failed, outcome, choices, said, refusal, texts, empty, next, values), `skipped` (box ids passed by), `state` (`ws_flow_run_state()`: done or failed).
- Nothing is sent, written or kept: the screen sends the record and the outcomes chosen so far, the server walks again each time.
- Each box is answered by `ws_flow_box_try()` itself, called with `data.trial` = the stand-in run, the values carried so far and what the texts read (`fill`). Only `ws_flow_trial()` passes `trial`; the panel action and the API build their `$data` without it.

## Which record does it start with?
- `run_id`: an earlier run of this flow the person reaches; its context (trigger, event, payload, anchor) and subject are taken over, so `{{trigger.*}}` read what really started it.
- `contact_id`: a customer, checked as Run checks one (`ws_flow_run_contact_check()`), with the context Run would write (`ws_flow_run_context_new()`). Both together: 422 `contact_id`. Neither: the start gives no values.
- An order or form entry that never started a run cannot be chosen: its event payload exists only when the event is sent. The canvas's picker: [[workspace-flows-trial-canvas]].
- The stand-in run (`ws_flow_trial_record()`, id 0) is never written; a send to the record's person reads its subject.

## How is each box settled? (`ws_flow_trial_settle()`, pure)
- A try that is refused, or a read that fails: failed, on its failed arrow where one leaves it (`ws_step_settlement()`). A start: done on `next` (a refused start: failed, nothing walked).
- Task, approval, Pinegrap AI, agent (`ws_flow_trial_person_kinds()`): the outcome the person chose (`outcomes`), else the first of `ws_flow_trial_choices()`; `choices` lists them. An unknown box or outcome in `outcomes`: 422 `outcomes`.
- End, and a Wait whose passed moment ends the run: done without outcome. Any other box without an outcome (a loop without its list): failed. The rest: the try's outcome.

## Which values are carried on? (`ws_flow_trial_data()`)
- Each step gives `text`, `outcome`, `status` (as `ws_step_node_values()`) and its values as text.
- Read with the owner's rights: Read a record, Read a row, Read rows (their `ws_step_*_do()`, which write nothing) and Read the records of a list (`ws_flow_records_window_try()`, no window moves). A condition on a value reads the carried values (`ws_flow_condition_value_holds()`, now also in a box's try with a run).
- A Wait gives `waited_until` (the moment it would end), never waits. A For each gives `count` and its first item (`index` 1, `item`, fields): the loop's boxes are walked once.
- A box that sends, writes or calls gives nothing more (a message id).

## Who may, and where is it offered?
- The owner alone, as a box's try (`ws_flow_share_manager()`, 403 before anything is read); an owner off the staff: 422.
- Panel action `ws_flow_trial` (flow_id, run_id, contact_id, outcomes as an object or its JSON), answer `{trial: …}`; listed in the ws_flow list of `includes/panel/actions.php` and in `ws_flow_actions_without_workspace()`.
- API `GET /workspace/flows/{id}/trial?run_id=&contact_id=&outcomes=ask:rejected,t1:cancelled` (`workspace.flows.trial`, `workspace:read`, `'mcp' => false`), answer `WorkspaceFlowTrial` with `WorkspaceFlowTrialStep[]`; the assistant's application is refused with 403 and a `run_id` must pass `ws_api_flow_run_reachable()`, as the box try's route.
- A web address start now answers in a box's try instead of "cannot run yet".
- Tests: `tests/workspace_flow_trial_test.php`.
