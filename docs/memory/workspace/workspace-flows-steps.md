---
name: workspace-flows-steps
description: Step boxes of a flow (steps.php, F4a PR #1835): post and email as box kinds run by ws_scheduled_do(), outcomes done/failed, owner's rights at publish and run, {{node.ID.FIELD}}, never run twice
---

## Where is it?
- `includes/workspace/steps.php` (loaded after flow_runs.php); tests `tests/workspace_flow_steps_test.php`. Canvas forms `postFields()`, `emailFields()` in `assets/js/workspace_flow.js`.

## What is a step box?
- A scheduled action's step as a box kind of its own: `ws_step_kinds()` (post, email so far; F4b adds the rest). `ws_flow_kinds()` merges them with `step => true`, outcomes `ws_step_outcomes()` (done, failed).
- Config as the step keeps it (`ws_step_config_normalize()`): post `channel_id`, `body`; email `to` (one text, commas), `subject`, `body`, `page_id`. `ws_step_config_problems()` gives `bad_config` lines in `ws_flow_graph_check()`.
- `ws_step_action()` turns config into the step array, so `ws_scheduled_action_input()` and `ws_scheduled_do()` run unchanged.

## Whose rights?
- Publish: `ws_flow_publish_check()` runs `ws_step_check()` on every step box with the publisher (the owner).
- Run: `ws_flow_step_run()` needs the owner (`actor_id`) still on the staff, then `ws_step_run()` checks again with their rights now; a refusal settles like a failure.

## How does it end?
- `ws_step_settlement()`: ok → done/done; failed → done/failed when an arrow leaves on failed, else failed (the run fails).
- Result data: post keeps `message_id`.

## What does {{node.ID.FIELD}} read?
- `ws_step_node_values()` over the run's settled boxes: `text` (sentence), `outcome`, `status`, and scalar `result.data` values; anything else is ''.
- `ws_step_fill_action()` fills only the kind's `texts` (post body; email subject, body) before `ws_scheduled_do()`, which then fills `{{date}}` and the rest; `lines` (subject) lose line breaks after filling. Addresses are never filled.
- Not added to `ws_scheduled_placeholders()`: that list is the scheduled form's and the parity test reads it.

## Why never twice?
- Before the work `ws_step_run()` writes effect `began:<kind>` (conditional on running). A stale box with it fails (`ws_flow_effect_began()`, `ws_flow_stale_action()` → fail): a post or e-mail may have gone out. F5 brings retry and skip by hand.
