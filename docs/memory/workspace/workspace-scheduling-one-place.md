---
name: workspace-scheduling-one-place
description: Scheduled work set up only on the flow canvas (no Scheduled flow window; channel_message template), Start a flow only where one can start, no new start_action boxes (#8394)
---

## Where is scheduled work set up?
- Only on the flow canvas, with its "At a time" start box: Flows › New flow, or a channel's Add › New flow, which opens the same gallery and canvas with that channel as home (`workspace-flow-layer.md`). Owner decisions: `workflow/decisions-made-product-41.md`; plan `plans/workspace-scheduling-one-path.md`.
- The composer's Add › Scheduled flow window is gone (PR #8394). It could only post one message in the channel at a time, beside a canvas that does everything; the owner found the two ways in confusing. Flows it made are ordinary flows and run on.
- Its one job is the gallery template `channel_message`, "Message to a channel at a time" (team, first): a time start, every working day at 09:00 from tomorrow, then a post box. Its setup asks the time, the channel and the words; the setting type `text` (`ws_flow_template_setting_types()`) is checked trimmed, not empty, at most 4000 characters, as a post box keeps. A channel setting whose box has none is offered the flow's home (`setupControl()` in workspace_flow.js).
- Scheduled messages (Send later beside the composer's Send, /schedule, their list) are a separate thing for any member and stay as they are.

## Where is it listed?
- In the Flows table; its start column says, for the template's default, "Every working day 09:00". The converted actions' own group, the next run in the start column and Run now on the canvas are steps 2 and 3 of the plan. Until then the group "Converted from scheduled actions" is still drawn (`workspace-flows-scheduled-section.md`).

## Which entries does a channel's Add list keep?
- Start a flow: only where `ws_channel_open` answers `flows_here`. `ws_flows_startable_in()` asks the drawer's own list (`ws_flows_runnable_in()`), only for whoever the Workspace shows Flows to, in a public or private channel, never for a discussion. A run started there opens its tasks in the channel and leaves its card there; the Flows screen's Run uses the flow's home (`workspace-flows-channel-run-card.md`).
- New flow: the layer on a new flow homed in the channel, for staff who build flows.

## Which box starts other work?
- "Start a flow" (`start_flow`, `workspace-flows-chain-box.md`). `ws_flow_kinds_not_offered()` lists `start_action` ("Start a scheduled action"): the palette (`PALETTE`) and Pinegrap AI's catalogue (`ws_flow_ai_catalogue()`) leave it out. The API's kind list (`ws_flow_kind_catalogue()`) keeps it, as saved flows do: the conversion made such boxes of old trigger steps, and they keep their form and run.

## What did the old scheduled actions form have that the window lacked?
- Compared with the form's last state (before 2befcfbe4c): starts by time, chain, join and event; conditions; every step and follow-ups; placeholders; the next run times. The canvas has all of these but the next run time, which step 3 brings to the flow list. The old rows are not in use (owner), so nothing is built for them; they keep running.

## Tests?
- tests/workspace_flow_templates_test.php (`channel_message`, the text answers), tests/workspace_flow_run_cards_test.php (offered only with a flow), tests/workspace_flow_draft_test.php (not offered kinds, the draft card's embed), tests/workspace_flows_canvas_test.php (the setup panel's words and home), tests/workspace_scheduled_convert_test.php (no window).
