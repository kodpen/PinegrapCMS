---
name: workspace-flow-ai-hints
description: What Pinegrap AI is told beyond the box catalogue when it drafts a flow: a start box's subject, a condition rule's shape, approval against condition, and the hints a retry carries
---

## Why hints at all?
- From the catalogue alone (`subject (text)`, `rule (object)`) the model wrote a flow's name as a Started by hand box's subject (`pinegrapaimakeflow` once normalized), drew a yes-or-no question as an event box (a start box) and left a condition box without its rule; the problem texts sent back named none of what would fix them, so the second try failed the same way and the card showed "It would not run".

## What does the catalogue carry? (`ws_flow_ai_catalogue()`)
- `ws_flow_ai_catalogue_hints($catalogue, $subjects)` (pure, flow_draft.php) adds `hint` to two settings, printed after the type by `ws_flow_ai_catalogue_text()`: `manual.subject` "one of <subjects>; the kind of record a run is about, left out when the flow is about no record in particular" (the keys of `ws_scheduled_record_types()`, kept on the setting as `subjects`; "left out" alone when there are none), `condition.rule` the shape of `ws_flow_ai_rule_shape()`: a value rule with `ws_flow_condition_value_operators()`, weekdays, hours, workday; a count or a record rule is chosen on the canvas, so it is not described.
- `ws_flow_ai_catalogue()` reads the kinds from the site, so it is not pure; the MCP catalogue (`flow_catalogue.php`) is unchanged.
- The tool's description adds `ws_flow_ai_box_choice_text()`: a box that starts the flow (the catalogue's `starts` kinds) asks nothing and no arrow leads into it; a yes-or-no question put to people in a channel is an approval box (arrows on approved and rejected); a condition box only compares a value and asks nobody. The sketch prompt (`ws_flow_ai_sketch_messages()`) lists the same catalogue.

## What does a retry carry?
- `ws_flow_ai_problem_hints($graph, $catalogue)` (pure): the subject sentence with the kinds when a manual box's subject is none of them, the rule's shape when a condition box has no rule type, and the box-choice text when the graph holds a second start box or an arrow into one. `ws_flow_ai_propose()` appends them to the problems of the first try; `ws_flow_ai_sketch_draft()` to the sketch's "It could not be published like this".
- Not refused at read time: `ws_flow_ai_node()` still takes any subject word, and `ws_flow_problems()` reports an unknown one as before; only what goes back to the model changed.

## Tests
- tests/workspace_flow_draft_test.php: `test_ws_flow_ai_catalogue_hints`, `test_ws_flow_ai_problem_hints`, the sketch's second try, the wiring of `ws_flow_ai_catalogue()`.
