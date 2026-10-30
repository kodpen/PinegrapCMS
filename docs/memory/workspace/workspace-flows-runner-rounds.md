---
name: workspace-flows-runner-rounds
description: Flow runner rounds (#3473, bug #3368): each run gets 3 boxes a round, new rounds until 30 a call or the budget; pending publish warning for loop items x body boxes
---

## How does one call share its boxes? (`ws_flow_advance_boxes()`)
- Batches of ready boxes, oldest first; a round gives each run at most `WS_FLOW_BOXES_PER_RUN` (3), then the run goes in `$full` and later batches leave it out.
- When no run under its share has a box to start and some run had its share, a new round clears `$full` and the counts; a batch starting nothing with `$full` empty ends the call.
- The call stops at `WS_FLOW_ADVANCE_MAX` (30) boxes or the deadline (`WS_FLOW_BUDGET_TICK` 10 s, `WS_FLOW_BUDGET_JOB` 25 s).

## Why rounds?
- Before #3473 a run that had its share was never picked again in that call: a lone run advanced 3 boxes per tick or job (a 200-box run took over an hour on the minute job) while the budget went unused. Rounds keep the fairness (each run's share per round is unchanged, two runs alternate) and let a lone run walk on.

## What is still open from #3368?
- A publish warning in the graph check (`flows.php`, `ws_flow_loop_problems()` neighbourhood) when a For each box's item cap (`WS_FLOW_LOOP_ITEMS_MAX`, 50) times its body boxes, plus the rest of the graph, can pass `WS_FLOW_MAX_BOX_RUNS` (200): today the run fails on the 200th box, after the work is done.
