---
name: workspace-scheduled-draft-ai
description: Pinegrap AI's propose_scheduled_action: a sentence drafted in a call of its own, kept under the answer as a flow draft card (C5b leftover), the model's texts and final words, when it is offered
---

## When is it offered?
- `ws_scheduled_draft_ai_offered()`: a request in a channel (no note, task or MCP alias), by somebody who may schedule, where the draft can be made. Beside it `propose_flow` takes boxes and arrows; this tool takes only a sentence.

## How is it drafted and kept? (C5b leftover, #3004)
- `propose_scheduled_action` (ai.php) stays: the model sends one sentence, and `ws_scheduled_draft_ai_step()` drafts it in a call of its own (`ws_scheduled_draft()`), then `ws_scheduled_draft_flow_row()` with the request's channel and the draft's notes: no `ws_scheduled_save_check()`, since a draft may leave parts open; `ws_flow_save_check()` still refuses (flow limit), and the model is told it could not be drafted.
- The row goes into `$state['proposals']['scheduled']` (the per-answer limit of 2 counts it); `ws_scheduled_draft_ai_store()` keeps only `type flow` rows (`ws_scheduled_draft_ai_rows()`, pure): an old-shape draft in a request asked before the upgrade is left out.
- The model's texts and the final words (`ws_ai_proposed_line()`, `ws_ai_proposed_words()`) name the flow: "I drafted the flow below. Nothing runs until you open it and publish it." Before, they sent the person to the scheduled action form, retired in R4 (#2970), and the card was record_type scheduled.

## Tests
- tests/workspace_scheduled_draft_test.php (`test_workspace_scheduled_draft_ai_keeps_flow_cards`, `test_workspace_scheduled_draft_ai_words`), parity table group `ai_drafts` (tests/workspace_flows_parity_test.php).
