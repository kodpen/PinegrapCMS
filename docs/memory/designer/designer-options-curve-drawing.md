---
name: designer-options-curve-drawing
description: The drawing of the easing curve and "Page transition" (plan designer-options-census, DO19 and DO20): the sheet, its seven boards, which step builds which, what is new, where it differs from the plan
---

## Where is the drawing?
- `designer/designer-options-curve-drawing/`: `options-curve.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. The designer's own parts are dark in both pictures.
- The sample is the page "Home" of the design "Still Water Yoga": the hero button's Transition, a card's entrance, the hero picture kept across "Home" and "Classes". Names and values are examples; the labels are the steps'.
- The curve as drawn: `designer-options-curve-drawing-curve.md`. "Page transition" and "Keep across pages": `designer-options-curve-drawing-pages.md`.

## Which board belongs to which step?
- DO19: E1 "Easing" with a curve (the list, "Custom curve…", Interactions with a stored curve, a stored curve changed); E2 the graph (a handle held, an overshoot, "Save as…", the typed numbers and their refusals); E3 the stored curves in Settings, Theme, "Variables", removing one that is used, the panel at 240 px; Z1 the graph twice enlarged, the keys and what is read out.
- DO20: P1 "Page transition" in Settings, Theme (None, Fade, Slide, its Easing with a curve); P2 "Keep across pages" on an element (off, on, the names, the three refusals); P3 the canvas tag and three moments of one transition in the Preview.

## What does the drawing add to the designer's bars?
- Nothing: no button in the Options panel's head, the page bar or the canvas bar (`designer-light-and-simple.md`). One small button at an Easing row's end, one section in Settings, Theme, a sub-head with two rows in the Interactions card.

## Which parts are drawn from what exists, and which are new?
- As today: the identity row, the tabs, the section card, a row with its changed dot, a list with its tick, a field, a slider with its number, the switch, the info and warn lines, the quiet small buttons, the Interactions card's rows and "Play", Settings, Theme with its two tabs, the "Colour mode" section and its button group, a row of "Your own" in "Variables" with "Used in n places" and the bin.
- New: the curve's sign in a field and in a list (blue for a stored curve); the button "Edit the curve" at an Easing row's end; the editor under the row (graph, the bead's track, the numbers, "Play", "Save as…", "Update"); the group "This design's curves" in the Easing list; a curve's row in "Your own"; the "Page transition" section with its figure of two pages; the sub-head "Between pages" with "Keep across pages" and "Name"; the link mark and name on a kept element's selection tag.

## Where does the drawing differ from the plan's words?
- The plan says a curve "is kept in the stored list under a name": the drawing makes that list the design's own variables (Settings, Theme, "Variables", "Your own"), so no table or column is added.
- The plan offers the curve "wherever Easing is chosen (Interactions, DO11's Transition)": the drawing gives "Page transition" the same Easing row.
- The plan says "Page transition" is "for the design" and names no place: Settings, Theme, tab "Look and colours", under "Colour mode", written into the design's variables file.
- The plan lists None, Fade, Slide: the drawing adds "Duration" and "Easing" to both, and "From" (The right, The left, Below) to Slide.
- The plan says "Keep across pages" writes "the same `view-transition-name` on both pages": the drawing joins the two by a "Name" the operator reads and picks, and says where nothing is kept.
- The drawing wins where the two differ (`workflow/architect-draws-new-part.md`); what neither says the worker asks of the architect.

Related: docs/memory/designer/options-panel-census.md, docs/memory/designer/designer-options-transition.md, docs/memory/designer/designer-interactions-part.md
