---
name: workspace-flows-delay-box
description: The Wait box ("Bekle", kind delay) of a flow (F5b #2135, until mode B4): for a time or until a date from a value, at most 90 days, waits with wake_at, settles done with waited_until, try, canvas
---

## What does it do?
- Kind `delay` in `ws_flow_kinds()` (not a step): outcome `done` only, output `waited_until` (text, ISO UTC from `ws_flow_time()`; conditions compare that). Filled into a message, chat post, notice or e-mail it is the site's date and time in the site's zone (`ws_step_values_local_times()` in `ws_step_fill_action()`, `30.10.2026 03:50`); a generic webhook body, MCP arguments and table cells keep ISO (#3515, bug #3373). Helpers in `includes/workspace/flow_runs.php`; tests `tests/workspace_flow_delay_test.php`.
- Settings `{mode, at, amount, unit}` (`ws_flow_delay_normalize()`): mode `for` (default, a time to wait) or `until`; `at` one line of 200; unit minutes, hours or days; amount '' when unset (in until mode the time before the date, may be empty or 0).

## What range, and why?
- 1 minute to `WS_FLOW_DELAY_MAX` = 129600 minutes (90 days), for both modes (owner, `workflow/decisions-made-product-33.md`): bookings and due dates fit, a year would leave stale runs. Retry waits keep a week (`WS_FLOW_RETRY_AFTER_MAX`).
- Out of range or unset: a `bad_config` publish problem (`ws_flow_delay_problems()`); a box out of range at run time fails.

## Until a date?
- `at` is one value (`{{trigger.start_at}}` of a booking) or a written date. `ws_flow_delay_moment()` reads an ISO time with its zone (`Z`, `+03:00`: flows' own times), a Unix time, or `Y-m-d( H:i)` in the site's zone (`mktime()`).
- `ws_flow_delay_until_date()`: the date less amount/unit; passed gives now, more than 90 days ahead or no date gives an error.
- Run (`ws_flow_delay_run_until()`): a value is filled (`ws_flow_derived_fill()`) from `ws_step_run_values()` with the owner's rights, so an owner off the staff fails the box. A passed date settles done at once ("The date has passed"); else it waits as below.
- Publish check: no `at`; a written `at` that is no date. `ws_flow_node_texts()` gives `at` only in until mode (reference check).

## How does it wait?
- `ws_flow_delay_run()`: one conditional UPDATE (running → waiting) writes effect and wait_key `delay:<until>`, `wait_event` '', `wake_at` = until, result "Waits until …" (the run view shows it). No sleep; a time needs nobody's rights.
- `ws_flow_due()` and `ws_flow_steps_recheck()` see it when `wake_at` passes; `ws_flow_step_recheck()` hands a `delay:` key to `ws_flow_delay_recheck()`, which settles it done/done (or puts `wake_at` back if the time has not come). A waiting box keeps the boxes after it unready and the run `waiting`.
- No event wakes it (`ws_flow_effect_wait()` null). Cancel skips it like any open box.

## Try and canvas?
- `ws_flow_box_try()`: the problem as refusal, else outcome done, "Waits 2 hours, …" and `next` = the moment it would end if reached now; until mode fills `at` from the chosen run (none: no date, a refusal); nothing waits.
- `workspace_flow.js`: palette and KINDS `delay` (`bi-hourglass`), `delayFields()` (mode, then `at` as a value field in until mode, amount labelled "This long before it", unit), meta `delayText()` ("2 hours before …"). `VALUE_FIELDS.delay` holds `at`, open only in until mode. AI hints `delay.mode`, `delay.at` (flow_draft.php).
- Tests: `tests/workspace_flow_delay_until_test.php`.
