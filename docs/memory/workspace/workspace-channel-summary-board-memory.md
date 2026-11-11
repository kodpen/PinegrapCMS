---
name: workspace-channel-summary-board-memory
description: The channel's Summary, Board and Memory tabs as cards and the channel panes' empty states in a dashed card (CL3 #8703); the workflow table's board and other screens' empty states unchanged
---

## How does the Summary tab look?
- `drawSummary()` (workspace.js) draws `.ws-summary-layout`: `section.card.ws-summary-card` with a head (`h3.h6`, Summary, Edit summary at its end) and a body (the text, the updated line), then `.ws-summary-side` with the customer card and Time spent (#8703).
- Edit summary opens the editor inside the card body (Save and Cancel as before); the pane is no longer cleared.
- Side by side (3fr / 2fr) while the pane is wider than 48rem (`@container` on `.ws-pane-summary`), one under the other below it and on a phone. With neither side card the summary takes the width (`.ws-summary-alone`).
- The card fills its column; `.ws-summary-view` keeps its 52rem reading width inside it.
- Time spent's three figures (`.ws-task-time-stat`) are tiles: border, small radius, a 3% tint, `auto-fit` columns of at least 7rem. The customer and Time spent cards lost `mt-7`: the layout's gap places them (a utility would beat the panel's layer).

## How does the Board tab look?
- `toolbar()` in workspace_board_channel.js draws chips: the grouping as `.ws-cb-chip` buttons in `.ws-cb-chips` (aria-pressed, `active`), the urgency and person selects as pills (`.ws-cb-chip-select`), Only mine and Show cancelled as `.ws-cb-chip` buttons with aria-pressed (they were switches; ids `ws-cb-mine`, `ws-cb-cancelled` kept; a press calls `load()` once, which draws the board, and the chip gets focus back). The search box is unchanged.
- Not `.ws-chip`: that is the message's @person, #task and department chip, and an unscoped rule of that name repainted every reference chip.
- On `.ws-pane-board` a column is a card (1px border, `.875rem`, `--pg-card-bg`); drop, no-drop, the current column and the phone picker work as before.
- The workflow table's board (`mountSource()`, `.ws-wt-board`) keeps its columns (`.ws-cb-col`, 2px transparent border, `--pg-bg-1`): it is not the channel screen. An empty column keeps `ws-cb-empty` without a dashed card: the column is already a card.

## How does the Memory tab look?
- `list()` in workspace_memory.js draws `.ws-memory-intro` (the hint and New record) above `.ws-memory-list`, a grid of `.ws-memory-entry` cards (`minmax(min(100%, 22rem), 1fr)`); no `list-group`. A record keeps pin, title, tags, Expired, text, meta, Edit and Delete; an expired one is at 75% opacity. `.ws-memory` lost `p-5` (the pane pads it); the form opens as before.

## How are empty channel panes drawn?
- `.ws-pane:is(.ws-pane-tasks, .ws-pane-decisions, .ws-pane-files, .ws-pane-summary, .ws-pane-board, .ws-pane-memory) .ws-empty` is a dashed card (1px dashed `--pg-border-color`, `.875rem`, `--pg-card-bg`). The grids' rules still make it span (`.ws-task-grid > .ws-empty`, `.ws-pane-decisions > :is(.ws-empty, .ws-skeleton)`, `.ws-memory-list > .ws-empty`). Memory's empty line is now a `.ws-empty`.
- Other screens' `.ws-empty` (Today, My tasks, the kit's panels, the scheduled lists) keep their look.

## Why?
- Owner (2026-11-10): the channel screen is "line after line"; `plans/workspace-channel-look/01-before-after.jpg` shows the summary as a card with Time spent beside it as tiles. CL3 of `plans/workspace-channel-look.md`.

## How was it checked?
- #8703: sandbox before/after at 1440 and 390 px, light and dark (Summary with and without text, Board with tasks and empty, Memory with records, empty and its form, an empty Tasks tab); `tests/workspace_channel_look_test.php`. Dev look after landing.
