---
name: workspace-today-join-about
description: Today's "Channels you could join" and "What is the Workspace?" cards in the dashboard design: markup, where their styles live, what the Join button does
---

## How is "Channels you could join" drawn?
- `homeJoin()` in workspace.js, framed by `todayCard('join', …)`, drawn only when `data.join` has channels; the badge counts them (`homeCount()`).
- A `ul.ws-today-join-list` with one `li.ws-today-join-row` per public channel the person is not in: the `#` sign in a tile-sized box, the name over topic and member count, Join as `.pg-dash-btn.ws-today-join-btn` (aria-label "Join: name").
- Join calls `ws_channel_join`, then `reloadChannels(id)` opens the channel; on an error the button is enabled again.

## How is "What is the Workspace?" drawn?
- `homeAbout()`: a `details.card.pg-dash-card.pg-dash-cardbox.ws-home-about` (`data-width` L), open for a newcomer; the `summary` is the card head.
- The text, then `ul.pg-dash-tiles.ws-today-about-tiles`, one `.pg-dash-tile` per part (channels, tasks, decisions; Claude and Pinegrap AI when on): title with icon, the line as `.pg-dash-tile-note`; as many tiles to a line as fit (13rem minimum).

## Where are the styles?
- ws_today.src.css sections "Channels you could join" and "What is the Workspace?" hold everything these cards need, the summary's chevron and marker included, and backend.src.css has no `.ws-home-*` rule left (`workspace-today-design.md`). The class `ws-home-about` stays on the card (tests/workspace_today_test.php).
