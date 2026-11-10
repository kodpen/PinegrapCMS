---
name: workspace-scheduled-draft-ai
description: Pinegrap AI's propose_scheduled_action: a sentence drafted in a call of its own, kept under the answer as a flow draft card (C5b leftover), the model's texts and final words, when it is offered
---

## When is it offered?
- `ws_scheduled_draft_ai_offered()`: a request in a channel (no note, task or MCP alias), by somebody who may schedule, where the draft can be made. Beside it `propose_flow` takes boxes and arrows; this tool takes only a sentence.
- "Where the draft can be made" is `ws_scheduled_draft_model_ready()`: while a model assistant runs (`ws_model_running()`), its own readiness, `ws_model_agent_blocked()` (missing or held, the test `ws_model_kick()` skips by); otherwise Pinegrap AI's `ws_ai_ready()`. `pg_ai_state()` asks the same before `ws_ai_missing()`, so the offered tool is not refused at the call. Panel and Pinegrap AI's own requests unchanged.
- Why: model assistants are not licence-gated (owner, #2418), and the draft goes to the assistant's own API (`pg_ai_chat()` → `ws_ai_http()` → `ws_model_http()`). Fixed (PR #8571, from code reading, not reproduced): an assistant on a site without Pinegrap AI's licence, or with it off or another app chosen, was never offered the tool; with a stale `ws_ai_ready()` static it could be offered and every draft fail.
- The draft's sentences name the assistant while it runs: `ws_scheduled_draft_sentence()` (not ready, unreadable answer), and `pg_ai_chat()`'s "did not answer" fallback (`ws_model_loop_sentence($agent, 'unreachable')`).

## How is it drafted and kept? (C5b leftover, #3004)
- `propose_scheduled_action` (ai.php) stays: the model sends one sentence, and `ws_scheduled_draft_ai_step()` drafts it in a call of its own (`ws_scheduled_draft()`), then `ws_scheduled_draft_flow_row()` with the request's channel and the draft's notes: no `ws_scheduled_save_check()`, since a draft may leave parts open; `ws_flow_save_check()` still refuses (flow limit), and the model is told it could not be drafted.
- The row goes into `$state['proposals']['scheduled']` (the per-answer limit of 2 counts it); `ws_scheduled_draft_ai_store()` keeps only `type flow` rows (`ws_scheduled_draft_ai_rows()`, pure): an old-shape draft in a request asked before the upgrade is left out.
- The model's texts and the final words (`ws_ai_proposed_line()`, `ws_ai_proposed_words()`) name the flow: "I drafted the flow below. Nothing runs until you open it and publish it." Before, they sent the person to the scheduled action form, retired in R4 (#2970), and the card was record_type scheduled.

## Tests
- tests/workspace_scheduled_draft_test.php (`test_workspace_scheduled_draft_ai_keeps_flow_cards`, `test_workspace_scheduled_draft_ai_words`), parity table group `ai_drafts` (tests/workspace_flows_parity_test.php).
