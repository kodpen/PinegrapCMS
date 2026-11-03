---
name: workspace-flow-ai-hints
description: What Pinegrap AI is told beyond the box catalogue when it drafts a flow, what is read for it (a lone retry, next after a step), and the hints a retry carries
---

## Why hints at all?
- From the catalogue alone (`subject (text)`, `rule (object)`) the model wrote a flow's name as a Started by hand box's subject (`pinegrapaimakeflow`), drew a yes-or-no question as an event box (a start box) and left a condition without its rule; the problems sent back named nothing that fixes them, so the second try failed alike (#4963).
- With those hints (dev, 2026-11-03) it drew the question as a post box (nothing waits for "yes"), sent an arrow on `next` from that post box and gave both posts a `retry` without `retry_after`: three problems, the yes branch missing.

## What does the catalogue carry? (`ws_flow_ai_catalogue()`)
- `ws_flow_ai_catalogue_hints($catalogue, $subjects)` (pure, flow_draft.php); `ws_flow_ai_catalogue_text()` prints a setting's `hint` after its type and a kind's after its label. Settings: `manual.subject` "one of <`ws_scheduled_record_types()` keys>; … left out when the flow is about no record in particular" (kept as `subjects`), `condition.rule` `ws_flow_ai_rule_shape()` (value with `ws_flow_condition_value_operators()`, weekdays, hours, workday; count and record rules are the canvas's), `approval.approvers` user ids of who answers (the asker for "I"), every step's `retry` and `retry_after` (go together). Kinds: approval is also any yes-or-no question (title the question, approved yes, rejected no); post only says something and never waits.
- `ws_flow_ai_catalogue()` reads the kinds from the site, so it is not pure; the MCP catalogue (`flow_catalogue.php`) is unchanged.
- The description adds `ws_flow_ai_box_choice_text()`: a start box asks nothing and no arrow leads into it; a yes-or-no question is an approval box (approved, rejected); a post box waits for no answer; a condition only compares. The sketch prompt lists the same catalogue.

## What is read for the model?
- `ws_flow_ai_settings_trim()` (pure): a box's `retry` with no numeric `retry_after` is dropped with it, as a box added on the canvas retries nothing; in `ws_flow_ai_graph()` and a patch's add (not a set, which may keep the box's own minutes).
- `ws_flow_ai_edge_outcome()` (pure): an arrow on `next` from a box that does not start the flow is sent with no outcome, so `ws_flow_graph_normalize()` gives the box's first (`done` after a step, `approved` after an approval); in `ws_flow_ai_graph()` and a patch's connect. Any other outcome is kept.
- Not refused: `ws_flow_ai_node()` still takes any subject word; `ws_flow_problems()` reports it.

## What does a retry carry?
- `ws_flow_ai_problem_hints($graph, $catalogue)` (pure): the subject's kinds when one was invented, the rule's shape when a condition has none, the box-choice text for a second start box or an arrow into one, each box's own outcomes when an arrow leaves it on another, `retry_after` when a box still retries with no minutes. `ws_flow_ai_propose()` appends them to the first try's problems; `ws_flow_ai_sketch_draft()` to its "It could not be published like this".

## Tests
- tests/workspace_flow_draft_test.php: `test_ws_flow_ai_catalogue_hints`, `test_ws_flow_ai_problem_hints`, `test_ws_flow_ai_graph_reads_retry_and_next`, the sketch's second try, the wiring of `ws_flow_ai_catalogue()`.
