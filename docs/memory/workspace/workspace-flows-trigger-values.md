---
name: workspace-flows-trigger-values
description: What a flow's step boxes read of what started the run (#2205): event and record values, the newcomer and the channel joined in their texts, a post box in the channel joined (-1)
---

## What does a box read of what started its run?
- `ws_step_run()` adds `ws_step_trigger_context()` of the run's context to every step's context: event + payload (an anchor leaves a payload over 8 KB out), newcomer + join channel. `ws_scheduled_fill()` then fills `{{event}}`, `{{record}}` … `{{form_fields}}` with the owner's rights, `{{newcomer}}`, `{{newcomer_name}}`, `{{channel}}`; the lead box reads its submission from it. Before, only the lead box had it and the parity rows of the join and event tokens were empty in a flow.

## How does a post box write in the channel joined?
- `channel_id` -1 kept by `ws_step_config_normalize()` (post only); `ws_flow_starts_on_join($graph)` (a `join` box in the graph) lets `ws_step_config_problems()` and `ws_step_check(…, $joined)` = `ws_scheduled_action_input(…, $joined)` take it at publish, run and try; without a join box it is a `bad_config` problem. Canvas: `postFields()` offers "The channel they joined" when the graph has a join box (`startsOnJoin()`), the box reads `fl_post_meta_joined`.

## What is left?
- A box try still leaves trigger tokens unfilled.
