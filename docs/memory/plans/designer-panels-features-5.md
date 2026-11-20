---
name: designer-panels-features-5
description: Plan designer-panels, features D54 to D62: the canvas bar in three zones: the Design and Prototype switch, screen sizes and zoom, the View menu, the Preview menu, Prototype mode in the same bar
---

## What is in the bar? (`canvas-bar.jpg`)
- (D54) Zone 1: a switch, Design | Prototype. Prototype is a mode of the bar, not one more button in its row.
- (D55) Zone 2: the six screen sizes as one single-choice group, the width as text ("1400 px · XXL"), then − 100% + and Fit. In an e-mail design the e-mail and phone widths stand in the group's place as today.
- (D56) Zone 3: a "View" menu whose badge counts the switches that are on. Its rows are switches named by what they do: Outline (O), Snap to grid (8 px), Dark canvas, Play animations. Then Find and History as icon buttons.
- (D57) A state is never written as a name: "Light" beside a tip "Dark canvas" becomes the switch "Dark canvas".
- (D58) Beside Publish a "Preview" menu: Preview in a new window, Open on the site (the saved state), Play as a prototype, Share the prototype…. "Open on the site" and "View page" leave the bar (they were the same thing twice; issue #10034).
- Architect (2026-11-17, agent channel message 8730) on D58 as built (DP23): the word is "Önizleme" (key `Preview`), not the drawing's "Önizle"; the button keeps `bi-window`, the play glyph is the Play row's alone; on a draft it stands before Save and Publish; below 768 px it is the glyph alone, after Publish.

## What does the bar show in Prototype mode?
- (D59) The same bar: the count ("37 pages · links read"), − 60% + and Fit for the map, "All arrows", "Tidy up", "Read again". No screen size, no View menu, no Close button: the switch leads back. Each mode keeps its own zoom.
- (D60) Everything the Prototype view's own bar holds when this step is claimed has a place in the mode's bar or in a menu of it; the step lists them in its PR body one by one.

## Which rules does the bar keep?
- (D61) Bar 44 px, a control 28 px, 6 px between controls of a group, a 1 px line and 12 px between groups. A switch, an action and a mode look different from each other.
- (D62) Narrow: the width text goes first, then the sizes fold into one dropdown; no control ever ends under a side column or off the screen, and none is removed (`designer/designer-narrow-screen-bars.md`). Shortcuts and what each control does are unchanged.
