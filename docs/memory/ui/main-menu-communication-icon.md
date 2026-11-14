---
name: main-menu-communication-icon
description: The Communication row's own drawing (row 9): a speech bubble with an arrow out and an arrow in, its colours and hover movement; the megaphone it replaced
module: output
---

## What does the Communication row draw?
- `pg_menu_icon('communication', 'campaigns-color')` (`includes/fn/output.php`), named by row 9 in `output_menu()`: a speech bubble (module tint and outline) with two arrows in the accent, stroke 1.9: `.pg-mi-out` pointing right above, `.pg-mi-in` pointing left below.
- Colours: the row's `campaigns-color`; the accent `--pg-mi-sky` (`#menu .pg-mi-communication`, "Main menu icons" in `backend.src.css`).
- The Workspace row also draws a bubble, with a tick and no arrows.

## How does it move?
- On hover or focus, inside the reduced-motion section: the outgoing arrow slides about 2 units to the right (`pg-mi-send`), the incoming one 0.12 s later about 2 units to the left (`pg-mi-receive`); both overshoot a little and settle in 0.6 s.

## Why not the megaphone?
- Owner (2026-11-14): the megaphone reads as campaigns; the row needs a sign of communication. Of the drafts he chose "6b: speech bubble and arrows"; an envelope, a paper plane, an @ sign, a handshake and arrows in a circle were not chosen.
- The megaphone's drawing (`campaigns`) and its movement (`pg-mi-jolt`, `pg-mi-emit`) were removed; nothing else drew them. `tests/menu_panel_test.php` checks the row.
