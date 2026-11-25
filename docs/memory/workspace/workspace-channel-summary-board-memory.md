---
name: workspace-channel-summary-board-memory
description: The channel's Summary tab as cards (pinned message, Time spent) and the channel panes' empty states as a dashed card (CL3 #8703); Board look in workspace-channel-board-look.md
---

## How does the Summary tab look?
- `drawSummary()` (workspace.js) draws `.ws-summary-layout`: `section.card.ws-summary-card` with a head (`h3.h6`, Summary, Edit summary at its end) and a body (the text, the updated line), then `.ws-summary-side` with the customer card and Time spent (#8703).
- The pinned message is the first card of `.ws-summary-side` (`summaryPinCard()`, BM2): head "Pinned message" with Show in the conversation, the writer and text, when and who pinned it. Drawn from `channel.pin` even where the reader closed the pin line; with no pin there is no card.
- Edit summary opens the editor inside the card body (Save and Cancel as before); the pane is no longer cleared.
- Side by side (3fr / 2fr) while the pane is wider than 48rem (`@container` on `.ws-pane-summary`), one under the other below it and on a phone. With neither side card the summary takes the width (`.ws-summary-alone`).
- The card fills its column; `.ws-summary-view` keeps its 52rem reading width inside it.
- Time spent's three figures (`.ws-task-time-stat`) are tiles: border, small radius, a 3% tint, `auto-fit` columns of at least 7rem. The customer and Time spent cards lost `mt-7`: the layout's gap places them (a utility would beat the panel's layer).

## How does the Board tab look?
- The tab and its toolbar: `workspace-channel-board-tab.md`; the figures, columns, cards and drop place (BM3, which replaced the card columns of #8759): `workspace-channel-board-look.md`; the Automations drawer: `workspace-channel-board-automations.md`.
- The grouping and "Only mine" are `.ws-cb-chip` buttons with aria-pressed and `active`. Not `.ws-chip`: that is the message's @person, #task and department chip, and an unscoped rule of that name repainted every reference chip.

## How does the Memory tab look?
- Rows under folding project headings since GM3a: `workspace-memory-tab.md`.

## How are empty channel panes drawn?
- `.ws-pane:is(.ws-pane-tasks, .ws-pane-decisions, .ws-pane-files, .ws-pane-summary, .ws-pane-board, .ws-pane-memory) .ws-empty` is a dashed card (1px dashed `--pg-border-color`, `.875rem`, `--pg-card-bg`). The grids' rules still make it span (`.ws-task-grid > .ws-empty`, `.ws-pane-decisions > :is(.ws-empty, .ws-skeleton)`, `.ws-memory-list > .ws-empty`). Memory's empty line is now a `.ws-empty`.
- Other screens' `.ws-empty` (Today, My tasks, the kit's panels, the scheduled lists) keep their look.

## Why?
- Owner (2026-11-10): the channel screen is "line after line"; the before/after picture he approved shows the summary as a card with Time spent beside it as tiles. CL3 of the channel look (`workspace-channel-screen.md`).

## How was it checked?
- #8703: sandbox before/after at 1440 and 390 px, light and dark (Summary with and without text, the Board as it was then with tasks and empty, Memory with records, empty and its form, an empty Tasks tab); `tests/workspace_channel_look_test.php`. Dev look after landing.
