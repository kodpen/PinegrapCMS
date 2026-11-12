---
name: workspace-channel-decisions-tab
description: The channel's Decisions tab draws each decision as a card of its own with a gap between them, not stacked like the conversation's messages
---

## How does the Decisions tab draw its list?
- `loadList('ws_channel_decisions')` (workspace.js) draws each message with `messageNode()` plus Show in the conversation; `.ws-pane-decisions` flows the cards in columns (#8759, `workspace-channel-panes.md`): each `.ws-msg` a card framed on four sides, its tag naming the kind; no coloured edge since the owner asked the green away (2026-11-11).
- In the conversation the same messages keep the rhythm of the ones around them.

## Why?
- Owner (2026-10-31): the decisions stood stuck one under the other; "belli bir standartta düzen olsun", as in the drawn design (`workspace-channel-screen.md`).
