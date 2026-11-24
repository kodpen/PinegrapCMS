---
name: main-menu-communication-icon
description: The Communication row's drawing (row 9): a speech bubble with an arrow out and an arrow in, in the Marketing violet, its hover move; the megaphone it replaced
module: output
---

## What does the Communication row draw?
- `pg_menu_icon('communication')` (`includes/fn/output.php`), named by row 9 in `output_menu()`: a speech bubble with an arrow pointing right above (`data-m="send"`) and one pointing left below (`data-m="recv"`), in the one line family of the menu (#11202).
- Colour: the Marketing category's violet on its tile (`pg_menu_category_tone(9)`), like every row; no second colour.
- The Workspace row draws the channel mark "#", so the bubble is the Communication row's alone.

## How does it move?
- On the row's hover or keyboard focus, inside `prefers-reduced-motion: no-preference`: the outgoing arrow slides 1.8 units to the right and back (`pg-mi-send`), the incoming one 1.8 units to the left (`pg-mi-recv`), once, 0.5 s (#11233, `main-menu-icon-motion.md`).

## Why the bubble and not the megaphone?
- Owner (2026-11-14): the megaphone reads as campaigns; the row needs a sign of communication. Of the drafts he chose "speech bubble and arrows"; an envelope, a paper plane, an @ sign, a handshake and arrows in a circle were not chosen. The redraw of 2026-11-22 kept his choice (`workflow/decisions-made-panel-17.md`).
- The megaphone (`campaigns`) and its moves are gone; `tests/menu_panel_test.php` checks the row.

Related: docs/memory/ui/main-menu-icons.md, docs/memory/ui/main-menu-icon-rows.md
