---
name: workspace-flows-delay-time
description: The Wait box's time of day (HH:MM, the site's zone, the first such moment at or after the wait's end) and, in until mode, "If the moment has passed: Go on / End the run" (MA9c, #9437)
---

## What does the time of day do?
- Optional "Time of day" (HH:MM, the site's zone, `ws_flow_delay_clock()`): the wait's end moves on to the first such moment at or after it (`ws_flow_delay_at_time()`, `DateTime` in the zone; a time a clock change skips reads as the zone reads it, 02:30 → 03:30). Both modes; for mode with amount 0 and a time waits until that time comes next.

## What if the moment has passed?
- Until mode: "If the moment has passed": Go on (default, a box drawn before keeps its way) or End the run: the box settles done with no outcome, as an End box ("The moment has passed, so the run ends here."). Passed = the date less the time before it, moved to its time of day, ≤ now.

## Why?
- A campaign profile's send time (`core/campaign-profile-flow-map.md`). Tests: `tests/workspace_flow_delay_time_test.php`.
- Settings, run and canvas of the box: [[workspace-flows-delay-box]].
