---
name: plan-board-grid
description: Plan board (workspace_board.php) grid columns and why .ws-board's min-width equals the grid minimum (#3815)
---

# Plan board grid

## How wide are the columns?

`draw()` in `assets/js/workspace.js` sets the template:
person `minmax(min(11rem, var(--ws-board-person, 14rem)), var(--ws-board-person, 14rem))`,
then one `minmax(var(--ws-board-day, 7rem), 1fr)` per day. The person column
shrinks from 14rem to 11rem before a day goes under 7rem.

## Why must `.ws-board { min-width }` match?

It is the grid's real minimum: 11 + 7 × 7 = 60rem (960 px). When it was
smaller (56rem against a 66.5rem grid), the grid overflowed its box and the
`overflow-x: auto` wrapper drew a scrollbar below about 1340 px with the main
menu open, cutting the Sunday column (#3815). Change both together:
`tests/workspace_board_grid_css_test.php` reads both sources and checks
min-width = person minimum + 7 × day minimum, and that it fits 1000 px.

## What about phones?

The phone block in `backend.src.css` sets `min-width: 0`,
`--ws-board-person: 7.25rem` and `--ws-board-day: 6.25rem`. The `min(11rem, …)`
keeps the person column at 7.25rem there instead of forcing 11rem.
