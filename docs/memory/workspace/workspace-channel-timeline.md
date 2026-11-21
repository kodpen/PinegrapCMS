---
name: workspace-channel-timeline
description: Conversation timeline beside the channel tabs (Channel info on a narrow screen): writer colours, waits, hover card with first words and tool result, click to open, live
---

## What is it, and why?
- Owner (2026-11-21): the current conversation of a channel as one strip in the tabs row, split by who answered and by the waits; pointing at it shows a card a little larger than a tooltip; a click opens the message; on a narrow screen it goes to the foot of Channel info. It holds no earlier version of a cleared conversation, and it is live.
- Drawn on look A's recipes after the owner found the first drawing dated: a thin rail a run, a capsule a turn, the card a floating surface with `pg-initials`, `pg-icon-tile` and `pg-pill`.

## How is the strip drawn?
- A turn is one writer's messages each within 120 s of the one before; a wait of 30 min or more starts a new run (`timelineRuns()`). A turn weighs √(its seconds + 240 a message), a wait √(its seconds); a turn is at least 6 px, less only when the strip is crowded (`timelineLayout()`). Runs stand 10 px apart.
- One colour a writer, by the order they first wrote (8 tones from `--pg-tone-*`), grey for system lines. A decision is an amber diamond over its turn, any other tool a small dot.
- In the tabs row a soft window marks the part of the conversation on the screen (`visibleMessages()` in workspace.js, the pane's scroll).
- Pointing brings the turn forward and dims the rest; in a turn of several messages a playhead marks the one on the card. Keyboard: the strip is a slider (arrows a message, Page Up/Down a turn, Home/End, Enter opens, Escape hides the card).

## What does the card say?
- The writer (initials in their colour, the kind for an agent, an application, a guest, a mail or the system), the time (with the date before today), the first 100 characters, up to two tools with what came of them, and the wait since the message before.
- Tools and results: decision or note (who marked it), task (status), file, picture, voice, poll or agent question (votes and the leading option, the winner once closed), approval (outcome, or how many approved), read receipt (how many read), status update (health), check-in, agent request (working, failed, answered).

## Which read is behind it?
- `ws_conversation_timeline($viewer, $channel)`: panel action `ws_conversation_timeline`, every reader of the channel (`ws_action_channel()`). The newest 600 messages after `ws_channel_floor()`, no deleted one and none the reader hid; one query a tool for the whole set.
- No API route and no MCP tool yet.

## When does it read, and where does it show?
- `assets/js/workspace_conversation_timeline.js` (bundled in `workspace.min.js`), mounted from `drawCenter()` (`head`) and `drawInfo()` (`info`). A read is kept 60 s per channel; Clear conversation drops it (`forget()`).
- Live: a sync that brings a new message reads again at once, and the new turns come in with a short animation; a vote or an answer reads again at most once in 8 s.
- The channel card is a grid (tabs, then the strip in the rest). Under 220 px left the script marks `.ws-app.ws-ctl-narrow`: the strip hides there and shows at the foot of Channel info with the first and last time and the writers as pills. A tap on a phone closes Channel info and opens the message. It hides while the phone keyboard is up and over an earlier version.

## How was it checked?
- PR #10936: sandbox at 1600 px light and dark, Channel info at 1280 px, iPhone 13 (scrub and tap); a message written by another writer reached the strip in about 2.5 s. `tests/workspace_conversation_timeline_test.php`.

Related: docs/memory/workspace/workspace-channel-overview.md, docs/memory/workspace/workspace-channel-panels.md, docs/memory/workspace/workspace-channel-identity-motion.md
