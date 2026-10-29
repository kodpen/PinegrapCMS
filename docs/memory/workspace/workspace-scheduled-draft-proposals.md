---
name: workspace-scheduled-draft-proposals
description: A connected app proposes a scheduled action (type scheduled in workspace.changes.propose): body, the save check, kept as a flow draft card since C5b, older scheduled cards, why never on a task
---

## Why a draft and not a create?
- An action runs in the name of whoever saves it, so create, change and resume stay `'mcp' => false` and the person's own (owner, #1708, answer 1b, `workflow/decisions-made-product.md`). A connected app proposes; the person opens it as a new flow of their own and publishes it.

## What does the application send?
- Superseded for new clients by `{type: flow}` (`workspace-flow-draft-proposals.md`); still taken unchanged.
- One more change in `workspace.changes.propose`: `{type: scheduled, action: draft, data, reason}`; action left out is draft. `data` is the action as an object (the routes that also took it were removed, #2107: `workspace-scheduled-actions-api.md`): name, rules (list), action (object), follow (list), channel_id (left out: the channel it is proposed in). Only the channel route's `changes` describes it (`ws_api_changes_param(…, true, true)`).

## What is checked, and when?
- `ws_changes_input()` takes type scheduled only when `$for_proposal`, before the record kinds, and hands it to `ws_scheduled_draft_proposal_row()` (scheduled_draft.php): the shape (`ws_scheduled_draft_proposal_input()`, pure), then `ws_scheduled_save_check()` for the person (`ws_ai_viewer()`). Any refusal answers 422 naming `changes[i]`, a dry run included.
- The 201 answer and GET /workspace/changes carry the card's words, built with the person's rights for ids the app chose, so they name only what the person may read: a task condition the person cannot see is refused (#1987). Pages need no check: staff open every folder.
- An assistant's answer (requests.answer) does not take it: Pinegrap AI drafts through `propose_scheduled_action` (`workspace-scheduled-draft-ai.md`).

## How is it kept and shown? (C5b, PR #2852)
- As a flow draft card, the same row a `{type: flow}` proposal makes (`workspace-flow-draft-proposals.md`): record_type `flow`, action draft, record_id 0. Before C5b it was a `scheduled` card the retired form opened.
- `ws_scheduled_draft_proposal_row()` after the save check calls `ws_scheduled_draft_flow_row()`: the home is `channel_id` (sent, else the channel it is proposed in) when public or private and read, else none; `ws_scheduled_draft_flow_snapshot()` (pure) turns `ws_scheduled_form_data()` of the checked row into `{sentence '', data {name, channel_id, graph}, notes, channel_id}` with `ws_scheduled_draft_flow_input()` (`ws_flow_graph_from_scheduled()`); notes are the reason, then the graph's `left` sentences. Then `ws_flow_save_check()` (flow limit, name, home) refuses with 422 on `changes[i]`; its name and graph are kept.
- Checked data, not the body (#1987): the body showed a made-up record name and money at a hundredth.
- The card, the API's list (`?type=flow`) and Open are the flow card's: `ws_flow_draft_change_entry()`, `ws_flow_draft_open()`.

## What about scheduled cards made before C5b?
- Older pending rows, an application's or Pinegrap AI's (before #3004), keep record_type scheduled: `ws_scheduled_draft_change_entry()` draws them, and `ws_flow_draft_open()` hands them to `ws_scheduled_draft_flow_open()`, which opens them as a new draft flow (home `ws_scheduled_draft_home()`). Apply stays refused (`ws_change_apply_check()`).

## Why not on a task?
- The task drawer (workspace_task_delegate.js) has no form to open a draft in; `workspace.tasks.changes.propose` refuses type scheduled with 422.

## Tests
- tests/workspace_scheduled_draft_flow_test.php (the flow input and snapshot), tests/workspace_scheduled_draft_test.php (input, home, the rehearsal, the data kept, read in a process of its own, the outline's money), tests/workspace_scheduled_test.php (task condition, `ws_scheduled_rules_as_sent()`), tests/workspace_proposals_test.php (where `ws_changes_input()` takes it, the routes), parity table group `ai_drafts`.
