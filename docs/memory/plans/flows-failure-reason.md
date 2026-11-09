---
name: flows-failure-reason
description: Plan (owner 2026-11-09): a failed flow box says which value came empty from which box, and offers "When empty, write" or the box itself; step FR1
---

## What is it for?
- A box that fails because a value was empty says only its own error. On dev a "Read a row" box found no row, and the next box failed with "Could not write in #genel: The message is empty." in five runs (372-374, 376, 378). The reader had to work out that the message was one value of the row box.
- The row box did say so on its own card ("The table … has no row … Nothing was read."), and "When empty, write" exists (`workspace/workspace-flows-empty-fallback.md`). The failed box links to neither.
- Decision: `workflow/decisions-made-workspace-10.md`. No schema.

## What exists already?
- `ws_step_fill_nodes()` fills the values (`ws_step_value_of()`, `ws_step_value_fallback()`) and returns the text only. `ws_step_fill_missing()` names the values that are not set and have no fallback, for the box try only (`ws_step_try_texts()`); a cell that exists but is empty is not named.
- A box's sentence and data are one JSON in `ws_flow_run_steps.result` (`ws_flow_result_pack()`), written by `ws_flow_step_settle()`. A failure reaches people through the run card (`ws_flow_run_card_payload()`), the bell line (`ws_flow_run_failure()`, `ws_flow_chain_notice_text()`) and the canvas's run view (`showRun()` in `workspace_flow.js`). Nothing is written to the error log.
- A row read that finds nothing is done, not failed: `found` 0 and empty cells (`ws_step_row_get_do()`); a later condition box decides.
- The canvas opens on a box by `?flow=&box=&run=` (`flowScreenAddress()`, `addressOptions()`). A value's chip opens "When empty, write" (`askFallback()`).

## Which steps are there?
- FR1 (S, no schema) The reason and the fix. When a box fails, the runner keeps in its result which values of its texts filled empty without a fallback: the box each came from and the value's name. The failure then carries a second sentence, "The value “Name” from the box “Read a row” was empty.", and, when that box is a row read that found nothing, that box's own sentence. It shows on the run card, in the bell line and in the run view. In the run view the failed box gets two buttons: "When empty, write" opens that value's fallback in a draft of the flow, "Open the box" selects the box. The run card's link opens the canvas on the failed box. A box that failed for another reason shows nothing new. Pure helpers for the empty list and the sentence, with tests; texts through `lang()` and `php tools/tr_add.php`. Memory: `workspace/workspace-flows-failure-reason.md`. Claim as `[plan:flows-failure-reason] FR1 — why a box failed`.

## What does it leave alone?
- Scheduling and "Start a flow": the scheduling plan of draft PR #8394 holds them. Its steps and FR1 both edit `workspace_flow.js`, so the claim check decides who waits.
- The row box stays done when it finds nothing. A publish hint for a text that may come empty is left for later.

## Done when?
- FR1 merged; the lasting points go to `workspace/` and this file is deleted.
