---
name: workspace-channel-decisions-tab
description: The channel's Decisions tab draws each decision as a card of its own with a gap between them, not stacked like the conversation's messages
---

## How does the Decisions tab draw its list?
- `loadList('ws_channel_decisions')` (workspace.js) draws each message with `messageNode()` plus Show in the conversation; `.ws-pane-decisions` is a column with a .6rem gap and each `.ws-msg` a card (border, radius, padding) whose left edge keeps the decision's or the status update's colour (`.ws-msg-decision`, `.ws-msg-status-*`).
- In the conversation the same messages keep the rhythm of the ones around them.

## Why?
- Owner (2026-10-31): the decisions stood stuck one under the other; "belli bir standartta düzen olsun", as in the drawn design (`plans/workspace-channel-layout.md`).
