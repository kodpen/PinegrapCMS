---
name: workspace-flows-task-event-person
description: A flow task box given to "The person the event names" (CH3b): the payload user_id, the required fallback people, where it is resolved, the gallery flow content_review_due
---

## What is it?
- A rule of the task box's "Given to" (kind `task`): `assign = 'event_person'`. The task goes to the team member the event that started the run names, its payload's `user_id`.
- `fallback` (up to 20 user ids) is kept only with this rule (`ws_flow_config_normalize()`); any other rule or picked people empty it. Publishing refuses the rule without fallback people (`ws_flow_graph_check()`).
- Architect (2026-11-16, agent channel message 7026): one general setting on the task box, never a box or setting for one event (`workflow/decisions-made-product-66.md`).

## Who gets the task?
- `ws_flow_task_event_person()` (pure, `flows.php`), called by `ws_flow_step_task_people()` (`flow_runs.php`) as the box runs.
- The named person when they are on the team and the flow's owner may give them work (`ws_is_team_member()`, `ws_can_assign_to()`).
- Else each fallback person who passes the same check. A run not started by an event, a `user_id` of 0 or missing, or a person who left the team falls back.
- Nobody left: the task opens with nobody on it and the box's result says so, as the other rules do.
- The rule is the flow's only: `ws_flow_task_assign_valid()` adds it to `ws_template_assign_valid()`, which channel templates keep using without it.

## How does the canvas show it?
- `taskFields()` in `workspace_flow.js`: the option "The person the event names" in Given to; chosen, it draws "When the event names nobody on the team" with the team's ticks (`peopleList(node, wrap, 'fallback', 20, label)`) and a help line. Ticking fallback people keeps the rule; ticking the box's own people drops it.
- The box's line on the canvas reads the rule's name (`ruleLabel()`).
- The API's `nodes` description and the box catalogue (`fallback`, a list of integers) name it.

## Which gallery flow uses it?
- `content_review_due`, "A page is due for review" (site), offered when `content.review_due` is in the person's events: the event start, then one task box "Review {{trigger.name}}", due in 7 days, normal priority, given to the person the event names.
- Its one setup setting is a person, written into `fallback` (the person using it until the setup says otherwise). Feature V16 of the content review plan.

## Tested?
- `tests/workspace_flows_test.php`: the publish check, the fallback kept for its rule only, who gets it.
- `tests/workspace_flow_templates_test.php`: `test_workspace_flow_templates_content_review_due`, plus the shared template checks (`content.review_due` is in the test fill's events).
- Sandbox (2026-11-17): the gallery flow published, three passed dates announced; the team member named got the task, a page with nobody and one with a person not on the team went to the fallback person; the problem shows with no fallback ticked.

Related: workspace-flows-runner.md, workspace-flows-template-adding.md, platform/content-review-announce.md
