---
name: workspace-scheduled-draft-proposals
description: A connected app proposes a scheduled action as a draft card (type scheduled in workspace.changes.propose): body, the save check, how it is kept, why never on a task
---

## Why a draft and not a create?
- An action runs in the name of whoever saves it, so create, change and resume stay `'mcp' => false` and the person's own (owner, #1708, answer 1b, `workflow/decisions-made-product.md`). A connected app proposes; the person opens it in the Scheduled actions form and saves it as their own.

## What does the application send?
- One more change in `workspace.changes.propose`: `{type: scheduled, action: draft, data, reason}`; action left out is draft. `data` is the action as an object (the routes that also took it were removed, #2107: `workspace-scheduled-actions-api.md`): name, rules (list), action (object), follow (list), channel_id (left out: the channel it is proposed in). Only the channel route's `changes` describes it (`ws_api_changes_param(…, true, true)`).

## What is checked, and when?
- `ws_changes_input()` takes type scheduled only when `$for_proposal`, before the record kinds, and hands it to `ws_scheduled_draft_proposal_row()` (scheduled_draft.php): the shape (`ws_scheduled_draft_proposal_input()`, pure), then `ws_scheduled_save_check()` for the person (`ws_ai_viewer()`). Any refusal answers 422 naming `changes[i]`, a dry run included.
- The 201 answer and GET /workspace/changes carry the card's words, built with the person's rights for ids the app chose, so they name only what the person may read: a task condition the person cannot see is refused (#1987). Pages need no check: staff open every folder.
- An assistant's answer (requests.answer) does not take it: Pinegrap AI drafts through `propose_scheduled_action`.

## How is it kept and shown?
- As Pinegrap AI's drafts: a `ws_ai_changes` row, record_type scheduled, action draft, requested_by the person; snapshot sentence '', data, notes (the reason, shown above the form) and channel_id.
- data is `ws_scheduled_form_data()` of the checked row: the shape the form reads a saved action in, a record named by its own name, money in minor units, addresses as a list, nothing else the app sent. Why (#1987): the body as sent showed a record name the app made up beside another record's id, opened a count in money at a hundredth and broke "Open in the form" on a `to` written as one line.
- The card's words (`ws_scheduled_draft_outline()`) turn money back into major units first (`ws_scheduled_rules_as_sent()`): the preview reads rules as the form sends them. Before #1987 Pinegrap AI's draft cards showed such a count a hundred times too high.
- The card (`ws_scheduled_draft_change_entry()`) carries the application's name as proposer and opens the form in the snapshot's channel (`ws_scheduled_draft_home()`), else the card's. Saved there, `ws_scheduled_draft_saved()` marks it applied. Apply stays refused (`ws_change_apply_check()`), over REST before that by `ws_api_proposal_decision_gate()`.

## Why not on a task?
- The task drawer (workspace_task_delegate.js) has no form to open a draft in; `workspace.tasks.changes.propose` refuses type scheduled with 422.

## Tests
- tests/workspace_scheduled_draft_test.php (input, home, the rehearsal, the data kept, read in a process of its own, the outline's money), tests/workspace_scheduled_test.php (task condition, `ws_scheduled_rules_as_sent()`), tests/workspace_proposals_test.php (where `ws_changes_input()` takes it, the routes), parity table group `ai_drafts`.
