---
name: workspace-flows-delay-box
description: The delay box of a flow (F5b, PR #2135): amount and unit up to a week, waits as a waiting box with wake_at, settles done with waited_until, problems, try, canvas form
---

## What does it do?
- Kind `delay` in `ws_flow_kinds()` (not a step): outcome `done` only, output `waited_until` (text, ISO UTC from `ws_flow_time()`). Helpers in `includes/workspace/flow_runs.php`; tests `tests/workspace_flow_delay_test.php`.
- Settings `{amount, unit}` (`ws_flow_delay_normalize()`): unit minutes, hours or days; amount '' when unset.

## What range, and why?
- 1 minute to `WS_FLOW_DELAY_MAX` = 10080 minutes (a week): the scheduled action's start delay (`trigger.delay`, at most 10080), which the parity table already maps to the delay box. Duration only: the scheduled delay never had a time of day.
- Out of range or unset: a `bad_config` publish problem (`ws_flow_delay_problems()`); a box out of range at run time fails.

## How does it wait?
- `ws_flow_delay_run()`: one conditional UPDATE (running → waiting) writes effect and wait_key `delay:<until>`, `wait_event` '', `wake_at` = until, result "Waits until …" (the run view shows it). No sleep; needs nobody's rights.
- `ws_flow_due()` and `ws_flow_steps_recheck()` see it when `wake_at` passes; `ws_flow_step_recheck()` hands a `delay:` key to `ws_flow_delay_recheck()`, which settles it done/done (or puts `wake_at` back if the time has not come). A waiting box keeps the boxes after it unready and the run `waiting`.
- No event wakes it (`ws_flow_effect_wait()` null). Cancel skips it like any open box.

## Try and canvas?
- `ws_flow_box_try()`: the problem as refusal, else outcome done, "Waits 2 hours, …" and `next` = the moment it would end if reached now; nothing waits.
- `workspace_flow.js`: palette and KINDS `delay` (`bi-hourglass`), `delayFields()` (amount, unit), meta `delayText()`.
