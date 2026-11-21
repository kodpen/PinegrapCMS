---
name: features-switchboard-design
description: Plan features-switchboard, the switch row (FS1): a card of rows with a tile, name, one line and a switch at the end, its Settings button, state pill and Open, 390 px; into Design examples by DX2b
---

## Where is the picture?
- `plans/features-switchboard/switch-rows.jpg`, drawn from `switch-rows.html` with the look A sheet kit; board A at 1440 px, board B at 390 px.
- Architect (2026-11-21, asked by the R&D agent for FS1, agent channel request 2258): a look built from parts the Design examples screen has, decided here.

## How is a switch row drawn?
- The card is one inner card of rows under a group label "Modules" with a grey count ("9 / 12 on"). A row is a `div`, never a link or a button, so it may hold controls.
- In a row, in this order: the icon tile (34 px, the module's tone), the name in the strong weight with one line under it saying what the module adds, then at the end a quiet small "Settings" where the module's settings sit in a folded row below, and last the switch (`input.pg-switch`).
- The name is the switch's `label`; the line is its `aria-describedby`. "Settings" opens and scrolls to the folded row (`pgSettingsFoldReveal()`), and is drawn whether the module is on or off: a switched-off feature's settings are never hidden.
- A module that is off: the tile grey, the name in the second text colour, and the line says what switching it off hides ("Off: ad areas do not show on pages or in the menu").
- A row the viewer may not change: the switch disabled, the row at half strength, the line says who may ("Only an administrator can change this").
- Commerce and Accounting: no switch; a state pill ("On" teal, "Off" grey) and a quiet small "Open" with an arrow, which opens their category.
- Under 576 px the name and the switch stay on one line, the line wraps under the name, and "Settings", or the pill with "Open", stand on a line of their own under it, in line with the name.

## Where does it enter the Design examples screen?
- As the pattern "Switch rows" of the Patterns group, drawn by step DX2b (`plans/design-examples-patterns-mail.md`) with its other patterns: the four kinds of row above (on, off with Settings, state with Open, not allowed), in both panes. FS1 waits for DX2b and copies it.
- The recipe: `pg-` names of the part in `backend.src.css`, a modifier of the rows recipe the folded settings rows use; no new tone, button rank or switch.

## What must be true?
- A test pins: every row has exactly one `input.pg-switch` with a `label` or one state pill with "Open"; no row is an `a` or a `button`; the posted names equal those before the step.
- "Screen check" at 1440 and 390 px against the picture in FS1's PR.

Related: docs/memory/plans/design-examples-patterns-mail.md, docs/memory/ui/settings-fold-rows.md
