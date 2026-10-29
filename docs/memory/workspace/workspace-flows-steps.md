---
name: workspace-flows-steps
description: Step boxes of a flow (steps.php, F4a #1835, F4b #1857, F4b2 #1923, F4b3 #1957): 13 kinds run by ws_scheduled_do(), the MCP box, outcomes, owner rights, {{node.ID.FIELD}}, never twice
---

## Where is it?
- `includes/workspace/steps.php` (loaded after flow_runs.php); tests `tests/workspace_flow_steps_test.php`. Canvas forms `postFields()`, `emailFields()`, `notifyFields()`, `taskOpenFields()`, `urlFields()`, `digestFields()`, `reportFields()`, `checkinFields()`, `startFields()`, `changeFields()` in `assets/js/workspace_flow.js`.

## What is a step box?
- A scheduled action's step as a box kind of its own: `ws_step_kinds()` gives each its step `type`: post, email, notify, `task_open` (type task: opened without waiting; the kind `task` is the box that waits), webhook, web_check, task_digest, report, checkin, `start_action` (type trigger; source action 0, the run's depth counts), change (`workspace-flows-change-box.md`), lead (`workspace-crm-lead-intake.md`), approval (waits for its request, `workspace-flows-approval-box.md`). `mcp`: `workspace-flows-mcp-box.md`. `ws_flow_kinds()` merges them with `step => true`, outcomes the kind's own `outcomes` or `ws_step_outcomes()` (done, failed).
- Config as the step keeps it (`ws_step_config_normalize()`, keys listed in its docblock); email `to` is one text, commas. `ws_step_config_problems()` gives `bad_config` lines in `ws_flow_graph_check()`.
- `ws_step_action()` turns config into the step array, so `ws_scheduled_action_input()` and `ws_scheduled_do()` run unchanged.

- Event and newcomer values, post in the channel joined: `workspace-flows-trigger-values.md`.
- Form lists: `ws_flow_js_config()` `steps` (metrics, `digest_only`, `targets`).

## Whose rights?
- Publish: `ws_flow_publish_check()` runs `ws_step_check()` on every step box with the publisher (the owner).
- Run: `ws_flow_step_run()` needs the owner (`actor_id`) still on the staff, then `ws_step_run()` checks again with their rights now; a refusal settles like a failure.

## How does it end?
- `ws_step_settlement()`: ok → done/done; failed → done/failed when an arrow leaves on failed, else failed (the run fails).
- Result data: every key the kind declares in `outputs`, written by `ws_step_result_data()` (`workspace-flows-outputs.md`).

## What do notify and webhook add?
- notify: the text gets `Flow “name”: ` in front; `ws_scheduled_notify()` writes a `separate` inbox line when there is no action row (id 0), and `ws_inbox_describe()` titles a notice with no action `Flow`.
- webhook: context `idempotency_key` (`ws_step_idempotency_key()`: `pg-flow-<run>-<box>-<pass>`) becomes an `Idempotency-Key` header; context `flow` {id, run, box} goes into the generic body as `flow` (parity line slice 3).
- The url of webhook and web_check is a secret (hook tokens in the path): `ws_flow_graph_present($graph, $edit)` masks it to `https://host/…` (`ws_flow_url_masked()`) for anyone without edit, on the screen, the API and run graphs; web check sentences name the host only.

## What does {{node.ID.FIELD}} read?
- `ws_step_node_values()` over the run's settled boxes: `text` (sentence), `outcome`, `status`, and scalar `result.data` values; anything else is ''.
- `ws_step_fill_action()` fills only the kind's `texts` (post body; email subject, body) before `ws_scheduled_do()`, which then fills `{{date}}` and the rest; `lines` (subject) lose line breaks after filling, escaped per destination (outputs file). Addresses are never filled.
- Not in `ws_scheduled_placeholders()` (a scheduled action's; the parity test reads it).

## Why never twice?
- Before the work `ws_step_run()` writes effect `began:<kind>` (conditional on running). A stale box with it fails (`ws_flow_effect_began()`, `ws_flow_stale_action()` → fail): a post or e-mail may have gone out. Its owner may retry it (it may send again) or skip it by hand (`workspace-flows-retry-skip.md`).

## Can a box be tried without running?
- Yes, from the canvas or the API: `workspace-flows-box-try.md`.
