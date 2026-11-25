---
name: designer-canvas-guides-drawing
description: The drawing of the canvas guides (DD8 #11550): where the sheet is, which board belongs to which step (CG2 to CG5), what is drawn over the canvas and in which colour, the readings it settles
---

## Where is the drawing?
- `docs/memory/designer/designer-canvas-guides-drawing/`: `canvas-guides.html` on the look sheet's stylesheet and its own `drawing.css`, with `canvas-guides-light.jpg` and `canvas-guides-dark.jpg`. Where the sheet and the built canvas guides differ, the topic files (`designer-canvas-guides-overview.md`) say what was built.
- The canvas bar is the designer's dark chrome in both pictures; the page on the canvas is a stand-in for a site's page.

## Which board belongs to which step?
- G1, step CG2: Measure. Alt held and pointing at another element: the distance between the facing edges and how far the tops are apart; pointing at the parent: the four inner distances.
- G2, step CG3: the spacing bands. At rest, dragging the bottom outer handle, Shift held, a handle reached from the keyboard, a part the operator may not edit, and the first-use line.
- G3, step CG4: a column's side edge dragged over the row's twelve columns, and an equal gap marked while a spacing handle moves.
- G4, step CG5: layout lines over the page, and the View menu with "Canvas guides".
- G5, steps CG2 and CG3: the canvas at 375.

## What is drawn over the canvas, and how?
- Selected: the full frame with its name. Pointed at: a dashed frame, its name on white.
- Measure lines and their numbers in the chrome's rose, with a short cap at each end; a number is whole pixels of the page, not of the zoomed canvas. A distance of 0 draws the shared edge's line and no number.
- Outer spacing: a hatched amber band outside the frame. Inner spacing: a plain teal band inside. Column and layout lines: the accent blue, the container's edges dashed.
- A handle: a white pill, 22 by 6 px, with an accent ring, on the band's edge; the same size at every zoom. A side with no spacing shows a faint handle on the frame's own edge, so spacing can be started from nothing.
- The value label beside the pointer: the scale's step in bold, then its pixels ("5 48 px"); with Shift or Alt the sides are named ("4 24 px · top and bottom"). A column handle's label: "6 of 12" and the screen size's name.
- An equal gap: both gaps carry the same number, and the label adds "= the gap above".

## What does it add to the bars?
- Nothing in the canvas bar. One switch row "Canvas guides" in the View menu under today's four rows, on by default, counted by the menu's badge; under it two plain lines naming the keys: "Measure: point at another element" with Alt, "Layout lines" with its key.
- The key rows are not buttons and are dimmed while the switch is off. The sheet drew Shift+G as an example; CG5 took Shift+G (`designer-canvas-guides-lines.md`).
- The first-use line under the bar: "Drag the edge of a tinted band to change the spacing. Hold Alt and point at another element to measure." with "Got it".

## Which states have no board?
- Nothing selected, or several selected: nothing is drawn and Alt does nothing.
- "Canvas guides" off: the selection frame as today.
- A value label that would leave the canvas flips to the pointer's other side; a number that does not fit its line sits beside it.
- Touch: a handle is dragged with a finger; Measure needs a keyboard and is not offered without one.
- Layout lines go with their key or Escape and follow the screen size the bar shows.

## What comes from a Design example, and what is new?
- The View menu's switch rows and the first-use line are the designer's own of today. The bands, handles, lines and labels over the canvas are new and live in the canvas's own layer, in the chrome's colours.
