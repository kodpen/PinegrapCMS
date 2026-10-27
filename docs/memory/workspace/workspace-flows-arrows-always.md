---
name: workspace-flows-arrows-always
description: Arrows on always in a flow (PR #2249): taken however the box ended, as a scheduled follow-up on always; which boxes have it, routing a failure, the timeout check, canvas
---

## What is an arrow on always?
- An arrow taken by any outcome of its box, the flows' answer to a scheduled action's follow-up on always. `ws_flow_edge_takes($on, $outcome)` (pure): always, or the same outcome; never a box without an outcome. The walk (`ws_flow_walk()`) uses it.
- Every box that is not a start and has outcomes may have it (`ws_flow_arrow_outcomes()`, used by `ws_flow_graph_check()`); start boxes keep next only, the end box has none.

## How does a failure go?
- `ws_flow_outcome_routed()` counts an arrow on always, so a step, start a flow, approval or task box with one ends done on failed, cancelled, expired or withdrawn, and the box after it runs. A box that fails with neither that outcome's arrow nor always still fails the run (the scheduled rule "one failing fails a good run").
- The task box's "arrow on timeout without a timeout" problem asks for an arrow drawn on timeout itself (`$always = false`): an arrow on always alone needs no timeout.

## Skipped and the cap of 5?
- A follow-up on skipped (the action's conditions did not hold) is a condition box's arrow on no. Flows have no cap of 5 arrows a box; their boxes are bounded by `WS_FLOW_MAX_NODES` (parity lines `WS_SCHEDULED_MAX_FOLLOW`).

## Canvas?
- `arrowOutcomes()` adds always to the outcome picker of a box an arrow may lead into; the arrow carries its label like failed and cancelled ("However it ended"); a run marks it taken whatever the box ended on.
