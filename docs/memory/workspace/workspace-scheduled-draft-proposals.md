---
name: workspace-scheduled-draft-proposals
description: A connected app proposes a scheduled action as a draft card (type scheduled in workspace.changes.propose): body, the save check, how it is kept, why never on a task
---

## Why a draft and not a create?
- An action runs in the name of whoever saves it, so create, change and resume stay `'mcp' => false` and the person's own (owner, #1708, answer 1b, `workflow/decisions-made-product.md`). A connected app proposes; the person opens it as a new flow of their own and publishes it.

## What does the application send?
- Superseded for new clients by `{type: flow}` (`workspace-flow-draft-proposals.md`); still taken unchanged.
- One more change in `workspace.changes.propose`: `{type: scheduled, action: draft, data, reason}`; action left out is draft. `data` is the action as an object (the routes that also took it were removed, #2107: `workspace-scheduled-actions-api.md`): name, rules (list), action (object), follow (list), channel_id (left out: the channel it is proposed in). Only the channel route's `changes` describes it (`ws_api_changes_param(…, true, true)`).

## What is checked, and when?
- `ws_changes_input()` takes type scheduled only when `$for_proposal`, before the record kinds, and hands it to `ws_scheduled_draft_proposal_row()` (scheduled_draft.php): the shape (`ws_scheduled_draft_proposal_input()`, pure), then `ws_scheduled_save_check()` for the person (`ws_ai_viewer()`). Any refusal answers 422 naming `changes[i]`, a dry run included.
- The 201 answer and GET /workspace/changes carry the card's words, built with the person's rights for ids the app chose, so they name only what the person may read: a task condition the person cannot see is refused (#1987). Pages need no check: staff open every folder.
- An assistant's answer (requests.answer) does not take it: Pinegrap AI drafts through `propose_scheduled_action`.

## How is it kept and shown?
- As Pinegrap AI's drafts: a `ws_ai_changes` row, record_type scheduled, action draft, requested_by the person; snapshot sentence '', data, notes (the reason, shown above the form) and channel_id.
- data is `ws_scheduled_form_data()` of the checked row: a record named by its own name, money in minor units, addresses as a list, nothing else the app sent. Why (#1987): the body as sent showed a made-up record name, money at a hundredth, and broke on a one-line `to`.
- The card's words (`ws_scheduled_draft_outline()`) turn money back into major units first (`ws_scheduled_rules_as_sent()`); before #1987 they showed it a hundred times too high.
- The card (`ws_scheduled_draft_change_entry()`) carries the application's name as proposer. Its "Open as a flow" (#2323) calls panel action `ws_flow_from_draft` → `ws_flow_draft_open()` → `ws_scheduled_draft_flow_open()` (written by `ws_flow_draft_opened()`): asker, pending, staff who may post there; `ws_scheduled_draft_flow_input()` (pure, `ws_flow_graph_from_scheduled()`) becomes a draft flow, home the snapshot's channel (`ws_scheduled_draft_home()`) when public or private and read, else none. The row goes applied with `flow_id` in the snapshot (record_id 0: the API's id is an action's); the card links "Open the flow". `left` is shown before the canvas opens. `ws_scheduled_draft_saved()` stays for a form opened with a change id until slice 5 retires the form. Apply stays refused (`ws_change_apply_check()`), over REST before that by `ws_api_proposal_decision_gate()`.

## Why not on a task?
- The task drawer (workspace_task_delegate.js) has no form to open a draft in; `workspace.tasks.changes.propose` refuses type scheduled with 422.

## Tests
- tests/workspace_scheduled_draft_flow_test.php (the flow input), tests/workspace_scheduled_draft_test.php (input, home, the rehearsal, the data kept, read in a process of its own, the outline's money), tests/workspace_scheduled_test.php (task condition, `ws_scheduled_rules_as_sent()`), tests/workspace_proposals_test.php (where `ws_changes_input()` takes it, the routes), parity table group `ai_drafts`.
