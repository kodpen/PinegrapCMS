---
name: workspace-flows-approval-answer
description: The answer an approval box asks with Approve (#7015, PR #7446): settings answer/answer_label, the check, where it is stored, the outputs answer, answer_number, decided_by
---

## What does the box ask?
- Settings `answer` (none, text, number; anything else is none) and `answer_label` (the question, 120 characters) in `ws_step_config_normalize('approval')`. A box with `answer` none is as before: same outputs, no prompt.
- Decided in #7015 (`workflow/decisions-made-product-33.md`); no schema.

## How does the card and the server know a request asks?
- No column: `ws_approval_answers_asked()` (approvals.php) finds the flow step waiting on `approval:<id>` (`wait_event` `WS_FLOW_APPROVAL_WAIT`) and reads its approval node's config. A request asked by a person, a scheduled action, an MCP or change box asks for nothing.
- `ws_approvals_map()` puts `answer` and `answer_label` on the card; `workspace_approvals.js` Approve then opens `h.ask()` with an input (the label as the question).

## What is checked?
- `ws_approval_decide_check()` takes the note as a 4th argument (null skips the answer check) and calls the pure `ws_approval_answer_problem()`: with an approval the answer is required; a number must give a value through `ws_approval_answer_number()` (`ws_calc_number()`, else `ws_calc_evaluate()` with every name a `ref`). A refusal's note stays free. The panel action, `ws_approval_decide()` and `POST /workspace/approvals/{id}/decide` (before its dry run stops) all pass the note.
- The answer is stored in `ws_approval_people.note` (255), as the refusal note.

## What does the box give?
- `ws_step_outputs()` adds `answer` (text), `answer_number` (number, kept as a string with decimals by `ws_step_result_data()`, null when unreadable) and `decided_by` (user id) when `answer` is set; the canvas `boxValues()` adds them the same way.
- `ws_flow_approval_recheck()` fills them from `ws_approval_answer_values()`: the last decider's note (rule all: the answer that settled it).

## Tests?
- `tests/workspace_flow_approval_answer_test.php`.
