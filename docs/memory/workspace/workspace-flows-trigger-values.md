---
name: workspace-flows-trigger-values
description: What a flow's step boxes read of what started the run (#2205, D5 #2341): {{trigger.<field>}} declared per start kind, event and record values, the newcomer, a post box in the channel joined (-1)
---

## What does a box read of what started its run?
- `ws_step_run()` adds `ws_step_trigger_context()` of the run's context to every step's context: event + payload (an anchor leaves a payload over 8 KB out), newcomer + join channel. `ws_scheduled_fill()` then fills `{{event}}`, `{{record}}` … `{{form_fields}}` with the owner's rights, `{{newcomer}}`, `{{newcomer_name}}`, `{{channel}}`; the lead box reads its submission from it. A run a start a flow box started also gets `source`, `source_result` (`workspace-flows-chain-box.md`). Before, only the lead box had it and the parity rows of the join and event tokens were empty in a flow.

## How does a post box write in the channel joined?
- `channel_id` -1 kept by `ws_step_config_normalize()` (post only); `ws_flow_starts_on_join($graph)` (a `join` box in the graph) lets `ws_step_config_problems()` and `ws_step_check(…, $joined)` = `ws_scheduled_action_input(…, $joined)` take it at publish, run and try; without a join box it is a `bad_config` problem. Canvas: `postFields()` offers "The channel they joined" when the graph has a join box (`startsOnJoin()`), the box reads `fl_post_meta_joined`.

## What does {{trigger.<field>}} read? (D5, PR #2341)
- Declared per start box like outputs: `ws_flow_trigger_outputs($kind, $config)` (flow_anchors.php). join: `newcomer_id`, `newcomer_name`, `channel_id`, `channel_name`; event: `event` (its key), `record_id` (`ws_events_record_of()`), plus `ws_flow_event_outputs()[event]`: the scalar payload keys its announcer always sends (lists never). row: table, row, actor and cells (`workspace-flows-row-box.md`). manual, schedule: none. Canvas: `ws_flow_js_config()` `trigger_outputs` (kinds, events), read by the value picker (`workspace-flows-value-picker.md`).
- Filled by `ws_step_trigger_values()` (pure) from the run context, under `WS_STEP_TRIGGER` among the box values, so `ws_step_fill_nodes()` (`WS_STEP_VALUE_PATTERN`) escapes it per destination like a box value. Every declared field is written; one the context lacks is ''. Only the context's trigger kind declares: a run another flow's box started (manual) carries the event but gives no trigger values.
- Rights: `ws_step_trigger_values_for()` gives record and payload only while the owner holds the catalogue right of the event's record and, for the workspace's own events, still sees it (`ws_events_creator_sees()`); else only `event`. Before, a payload value would have bypassed the gates `ws_events_fill_values()` keeps.
- Check: an undeclared field is `bad_reference` (`ws_flow_trigger_fields()`); no skippable warning, the start always ran. A box try fills them, and the scheduled tokens, from the chosen run (`workspace-flows-box-try.md`).
- Tests `tests/workspace_flow_trigger_values_test.php`.

## What does the waiting task box fill? (PR #2919)
- Its title and description, through `ws_flow_task_texts()` (flow_runs.php): box and trigger values from `ws_step_run_values()` (owner's rights now, shared with `ws_step_run()`), escaped as task_open's, then the scheduled tokens via `ws_scheduled_fill()`. Title one line, at most 200, the box's name when it fills empty. Before, both were written raw.
- A retry finds its task by owner and the box's line only (`ws_flow_step_task_find()`): a filled title may differ on the retry.
