---
name: workspace-flows-list
description: The Flows screen's flow list (#3172, #3222): full-width table, start sentence, state, last run flag, home channel, search, filter chips, the scheduled actions' groups at its end, "Flows ›" back
---

## What is it?
- The flow list, full width in place of the editor (`showNone()` → `listing(true)`, `drawFlows()`); a missing or unreachable `?flow=` shows it too, and so does deleting a flow. A table: Flow (row header, a button that opens it; a click on the row too), How it starts (V2's line icon of the start box and `start_text`, else the kind's name), State (`statusBadge()`), Last run (`runFlag()`, its link opens the flow then the run), Home channel (`#name`). Phones: stacked rows, each value under its column's name (`data-label`).

## How is it searched and filtered?
- A search field (every word in name, sentence or channel) and chips `CHIPS` (All, Time, By hand, Event, Join, Draft): a toolbar of `aria-pressed` buttons whose counts follow the search (`listCounts()`, `listMatches()`; Node test in the canvas test). They replace the start select of #2391.

## What does `ws_flow_list` add?
- `ws_flow_list` adds `start_text` (`ws_flow_start_sentence()`: a time box in short words, `ws_flow_start_short()`, "Every day 09:00"; other starts as the API's `start_text`) and `channel_name` (`ws_flows_channel_names()`, only channels the person reads), both read once for the list (`ws_flows_starts()`). The API's flow list is unchanged.

## What ends the list?
- For staff, two groups of scheduled actions under the table, outside the search and chips: "Converted from scheduled actions · N" (Open flow, Run now, History) and "Not converted · N" (their cards). No Scheduled actions section or entry since V5 (#3222); `?view=scheduled` opens the list: `workspace-flows-scheduled-section.md`.

## How does one get back to it?
- "Flows ›" (`flowsButton()`) in the editor's and the gallery's toolbar goes back (`showList()`, saving first); the flow's row takes the focus. A scheduled action drawn read-only goes back with its Back.
