---
name: workspace-flows-steps
description: Step boxes of a flow (steps.php, F4a #1835, F4b #1857): six step kinds run by ws_scheduled_do(), outcomes, owner rights, {{node.ID.FIELD}}, never twice, Idempotency-Key
---

## Where is it?
- `includes/workspace/steps.php` (loaded after flow_runs.php); tests `tests/workspace_flow_steps_test.php`. Canvas forms `postFields()`, `emailFields()`, `notifyFields()`, `taskOpenFields()`, `urlFields()` in `assets/js/workspace_flow.js`.

## What is a step box?
- A scheduled action's step as a box kind of its own: `ws_step_kinds()` gives each its step `type`: post, email, notify, `task_open` (type task: opened without waiting; the kind `task` is the box that waits), webhook, web_check. F4b2 adds task_digest, report, change, checkin, trigger. `ws_flow_kinds()` merges them with `step => true`, outcomes `ws_step_outcomes()` (done, failed).
- Config as the step keeps it (`ws_step_config_normalize()`, keys listed in its docblock); email `to` is one text, commas. `ws_step_config_problems()` gives `bad_config` lines in `ws_flow_graph_check()`.
- `ws_step_action()` turns config into the step array, so `ws_scheduled_action_input()` and `ws_scheduled_do()` run unchanged.

## Whose rights?
- Publish: `ws_flow_publish_check()` runs `ws_step_check()` on every step box with the publisher (the owner).
- Run: `ws_flow_step_run()` needs the owner (`actor_id`) still on the staff, then `ws_step_run()` checks again with their rights now; a refusal settles like a failure.

## How does it end?
- `ws_step_settlement()`: ok → done/done; failed → done/failed when an arrow leaves on failed, else failed (the run fails).
- Result data: post keeps `message_id`, task_open `task_id` (`ws_scheduled_task()` now answers it).

## What do notify and webhook add?
- notify: the text gets `Flow “name”: ` in front; `ws_scheduled_notify()` writes a `separate` inbox line when there is no action row (id 0), and `ws_inbox_describe()` titles a notice with no action `Flow`.
- webhook: context `idempotency_key` (`ws_step_idempotency_key()`: `pg-flow-<run>-<box>-<pass>`) becomes an `Idempotency-Key` header; context `flow` {id, run, box} goes into the generic body as `flow` (parity line slice 3).

## What does {{node.ID.FIELD}} read?
- `ws_step_node_values()` over the run's settled boxes: `text` (sentence), `outcome`, `status`, and scalar `result.data` values; anything else is ''.
- `ws_step_fill_action()` fills only the kind's `texts` (post body; email subject, body) before `ws_scheduled_do()`, which then fills `{{date}}` and the rest; `lines` (subject) lose line breaks after filling. Addresses are never filled.
- Not added to `ws_scheduled_placeholders()`: that list is the scheduled form's and the parity test reads it.

## Why never twice?
- Before the work `ws_step_run()` writes effect `began:<kind>` (conditional on running). A stale box with it fails (`ws_flow_effect_began()`, `ws_flow_stale_action()` → fail): a post or e-mail may have gone out. F5 brings retry and skip by hand.
