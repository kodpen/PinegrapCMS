---
name: designer-panels-measures-2
description: Plan designer-panels, the measure sheet as text, part 2: the Options panel's parts in order with their sizes, and every control's states (measures-2-anatomy.jpg, measures-3-controls.jpg)
---

## Which parts, top to bottom? (panel 336 px, it gives between 300 and 360)
- a. Identity 60. b. Tabs 40. c. Search 34. d. A section's head 36. e. A row 32: the dot 7 px, the label column 92 (78 when the panel is under 300), the control. f. The quick strip 52, on Content only. g. A closed section 36. h. The filter "Only what I changed" 38, always at the bottom.
- The dot keeps its place on every row. A badge is absent at 0. The filter puts one list in the tabs' place and stays where it is.

## Which states has each control?
- Button group: default, hover (`rgba(255,255,255,.04)`), selected (`#2563eb`, white, the shadow), locked (dimmed, no pointer). Up to six values; seven or more are a select. A click on the selected segment removes the value.
- Field and select: default; focus (accent border and ring); invalid (border `#ff6b6b`, one line under it, the value not written). The unit stands grey at the right.
- Changed dot: under the pointer an undo arrow with the tip "Reset to default"; a click is one undo step.
- Badges: a tab's is filled `#5b9bff` on the open tab, `#262c36` elsewhere; a section's as the passive one; the screen size "MD" or "all" in `#f5b54a`, a click opens the chooser; a state badge "EN · not translated" or "translated".
- Section card: open has the ground `#1f242c` and a 1 px line over its body; closed has no ground. The summary is a colour square, a single value as text, else "none" or "default".
- Box drawing: grey for an inherited or default value, filled blue for one set here, a frame for the one being typed; a value on an edge is 19 px wide; typed 0 to 5 or "auto"; Shift the opposite side too, Alt all four.
- Tabs: Left and Right move; hover `rgba(255,255,255,.04)`.
- Search: results 120 ms after typing; the first Esc clears, the second leaves; the clear mark has a 24 px target.
- Switch: 32 by 18, knob 12, 120 ms.
- Secondary button `#262c36` with border `#343c48`; primary `#2563eb`; icon button 28.

## What does the sheet not show?
- A colour, icon or picture picker: it stays as today, at height 30 and corner 9.
- The panel under 300 px: `designer-panels-measures-4.md`. Several elements selected: `designer-panels-measures-5.md`.
