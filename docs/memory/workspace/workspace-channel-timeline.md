---
name: workspace-channel-timeline
description: Conversation timeline at the end of the channel tabs row (Channel info when narrow): one well, days as columns, runs as bars, writer colours, the read, live updates
---

## What is it, and why?
- Owner (2026-11-21): a channel's current conversation as one strip in the tabs row, split by who answered and by the waits; pointing shows a card; on a narrow screen it goes to the foot of Channel info. No earlier version of a cleared conversation; live.
- Redrawn in TR1 (#11047) from the architect's drawing `workspace/channel-timeline-drawing/timeline.html` (boards 1 to 9, with `timeline-dark.jpg` and `timeline-light.jpg`) after the owner turned down the first build (`workflow/decisions-made-workspace-46.md`). Where the words and the drawing differ, the drawing holds.
- The well and a still card are the "Conversation timeline" pattern of the Design examples screen (`pg_look_examples_timeline_pattern()`): a change starts there.

## How is the well drawn?
- One well at the end of the tabs row: 30 px high, 220 to 420 px wide, `--pg-surface-inset` with a hairline (`--ws-ctl-line`), no shadow; the zoom buttons inside it after a hairline. No scrollbar; a cut end fades over 22 px.
- A turn is one writer's messages each within 120 s of the one before; a wait of 30 min or more, or a new day in the site's time zone, starts a run (`timelineRuns()`).
- `timelineLayout(runs, scale)`: x starts at 6 px; a turn is √(its seconds / 240 + its messages) × scale wide, at least 6 px (2 px under scale 2), 1 px between turns; runs stand √(wait / 1800) × scale × 1.4 apart, 8 to 26 px (3 px under scale 2); a new day is 8 px, a 1 px hairline, 9 px; 8 px after the last turn.
- A run is one 6 px bar of its turns, round only at its two ends, flat colour (the writer's tone at 82 % over the surface). A colour a writer by first-writing order (8 tones), grey for system lines. A decision is an amber diamond on its turn from scale 2, the strip's only mark.
- A day's date over its column: the short date from 34 px of column, the day number alone from 13 px (none for Today and Yesterday), else none; today's a step stronger. A long day's date stays 22 px from the port's left while its column passes, until 40 px before its end (`timelineDayLeft()`).
- The part of the conversation on the screen: a 2 px accent line under the bars (`.ws-ctl-here`, `visibleMessages()` in workspace.js), in the tabs row and in Channel info.

## Which read is behind it?
- `ws_conversation_timeline($viewer, $channel)`: panel action `ws_conversation_timeline`, every reader of the channel (`ws_action_channel()`). The newest 600 messages after `ws_channel_floor()`, no deleted one and none the reader hid; one query a kind of row for the whole set. No API route and no MCP tool.

## When does it read, and where does it show?
- `assets/js/workspace_conversation_timeline.js` (in `workspace.min.js`), mounted from `drawCenter()` (`head`) and `drawInfo()` (`info`, under the heading "The conversation", the writers as pills under it). A read is kept 60 s per channel; Clear conversation drops it (`forget()`).
- Live: a sync with a new message reads again at once and the new turns grow in; a vote or an answer reads again at most once in 8 s.
- Narrow: when the holder's content box is under 220 px (ResizeObserver) the script marks `.ws-app.ws-ctl-narrow`: the well hides in the tabs row and shows at the foot of Channel info. The holder keeps its padding, so the switch cannot swing to and fro. It hides while the phone keyboard is up.

## How was it checked?
- First build #10936, zoom #11027, the redraw #11047 (its screen check in the PR body). `tests/workspace_conversation_timeline_test.php`: the read's rows in PHP, the script's pure functions in node.

Related: docs/memory/workspace/workspace-channel-timeline-zoom.md, docs/memory/workspace/workspace-channel-timeline-card.md, docs/memory/workspace/workspace-channel-overview.md
