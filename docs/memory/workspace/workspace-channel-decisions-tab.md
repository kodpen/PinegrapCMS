---
name: workspace-channel-decisions-tab
description: The channel's Decisions tab draws each decision as a card of its own with a gap between them, not stacked like the conversation's messages
---

## How does the Decisions tab draw its list?
- `loadList('ws_channel_decisions')` (workspace.js) draws each message with `messageNode()` plus Show in the conversation; `.ws-pane-decisions` is a grid of cards (#8688, `workspace-channel-panes.md`): each `.ws-msg` a card whose start edge keeps the decision's, note's or status update's colour.
- In the conversation the same messages keep the rhythm of the ones around them.

## Why?
- Owner (2026-10-31): the decisions stood stuck one under the other; "belli bir standartta düzen olsun", as in the drawn design (`workspace-channel-screen.md`).
