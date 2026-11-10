---
name: workspace-flows-list
description: The Flows screen's flow list (#3172, #3222): table, start words with the next run, converted flows for all staff, search, chips, the Not converted group at its end, "Flows ›" back
---

## What is it?
- The flow list, full width in place of the editor (`showNone()` → `listing(true)`, `drawFlows()`); a missing or unreachable `?flow=` shows it too, and so does deleting a flow. A table: Flow (row header, a button that opens it; a click on the row too), How it starts (V2's line icon of the start box and `start_text`, else the kind's name), State (`statusBadge()`), Last run (`runFlag()`, its link opens the flow then the run), Home channel (`#name`). Phones: stacked rows, each value under its column's name (`data-label`).

## How is it searched and filtered?
- A search field (every word in name, sentence or channel) and chips `CHIPS` (All, Time, By hand, Event, Join, Draft): a toolbar of `aria-pressed` buttons whose counts follow the search (`listCounts()`, `listMatches()`; Node test in the canvas test). They replace the start select of #2391.

## What does `ws_flow_list` add?
- `ws_flow_list` adds `start_text` (`ws_flow_start_sentence()`: a time box in short words, `ws_flow_start_short()`, "Every day 09:00"; other starts as the API's `start_text`) and `channel_name` (`ws_flows_channel_names()`, only channels the person reads), both read once for the list (`ws_flows_starts()`). A live flow that starts at a time gets its next run in `start_text` ("Every day 09:00 · Next: Tomorrow 09:00", `ws_flows_next_runs()`), and staff get the converted flows somebody else owns as rows (`ws_flows_with_converted()`): `workspace-flows-one-list.md`, PR #8573. The API's flow list is unchanged.

## What ends the list?
- For staff, only "Not converted · N" under the table, outside the search and chips: the cards of the actions the conversion refused. Converted flows are rows of the table; their own group went in PR #8573. No Scheduled actions section or entry since V5 (#3222); `?view=scheduled` opens the list: `workspace-flows-not-converted.md`.

## How does one get back to it?
- "Flows ›" (`flowsButton()`) in the editor's and the gallery's toolbar goes back (`showList()`, saving first); the flow's row takes the focus. A scheduled action drawn read-only goes back with its Back.
