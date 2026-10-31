---
name: workspace-flows-runner-rounds
description: Flow runner rounds (#3473, bug #3368): each run gets 3 boxes a round, new rounds until 30 a call or the budget; publish warning for loop items x body boxes (#3515)
---

## How does one call share its boxes? (`ws_flow_advance_boxes()`)
- Batches of ready boxes, oldest first; a round gives each run at most `WS_FLOW_BOXES_PER_RUN` (3), then the run goes in `$full` and later batches leave it out.
- When no run under its share has a box to start and some run had its share, a new round clears `$full` and the counts; a batch starting nothing with `$full` empty ends the call.
- The call stops at `WS_FLOW_ADVANCE_MAX` (30) boxes or the deadline (`WS_FLOW_BUDGET_TICK` 10 s, `WS_FLOW_BUDGET_JOB` 25 s).

## Why rounds?
- Before #3473 a run that had its share was never picked again in that call: a lone run advanced 3 boxes per tick or job (a 200-box run took over an hour on the minute job) while the budget went unused. Rounds keep the fairness (each run's share per round is unchanged, two runs alternate) and let a lone run walk on.

## What warns at publish? (#3515, completes #3368)
- `ws_flow_loop_size_warnings()` (`flow_loops.php`, called from `ws_flow_loop_problems()`): `ws_flow_loop_box_runs()` counts each box that runs once (not the start, not a pure value box) plus each body box × `WS_FLOW_LOOP_ITEMS_MAX` (50); over `WS_FLOW_MAX_BOX_RUNS` (200) every loop with boxes gets a `loop_size` problem with warning true on its For each box. A body box behind a condition counts as if it ran.
- A warning, not a refusal: most lists are far shorter than 50 and the flow works for them; refusing would forbid a 4-box body outright. Before it, the run failed on the 200th box, after the work of the items before it was done.
