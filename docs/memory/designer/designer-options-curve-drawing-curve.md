---
name: designer-options-curve-drawing-curve
description: The easing curve as drawn for DO19 (options-curve.html, boards E1 to E3, Z1): the Easing list, the editor under the row, the graph's limits, stored curves as design variables, keys
---

## What does an Easing row gain?
- Wherever Easing is chosen (Transition in Shadow and effect, Interactions, "Page transition"), one list and one editor. Every line carries the curve's small sign, blue for a stored curve.
- The list keeps its lines (in Transition, first "Default", the look's), then a group "This design's curves" with the stored curves by name, then "Custom curve…".
- A small button at the row's end, tip "Edit the curve", opens the editor under the row, starting from whatever is chosen. No window. Esc shuts it; the curve stays.

## What is the editor?
- A square graph, time across and the change up: the two ends stay, two handles on arms from the ends are dragged; the dashed diagonal is Linear. Under it the bead's track, the four numbers in one field, then "Play" and "Save as…".
- A handle moves across only between the ends (0 to 1) and up or down as far as half a height past either line (−0.5 to 1.5), so a curve may overshoot; a line then says "Goes past the end and comes back."
- The numbers and the graph are one curve: either changes the other. Refused numbers mark the field: "Four numbers. The first and the third stay between 0 and 1."
- The bead runs along its track once each time the curve changes; its faint marks are where it stands at ten equal times. "Play" plays the element's own motion on the canvas (the entrance, or the change to Hover for a Transition).
- The element takes the curve when the handle is let go or the numbers are entered; a drag is one undo step. Under reduced motion the bead stands still and "Play" does nothing: "Your system asks for less motion."

## How is a curve stored and used?
- "Save as…" asks the name in place ("Save", "Cancel"); a taken name is refused: "A curve named “Snap” is there already." The row then reads the name.
- A stored curve is one of the design's own variables: a row of "Your own" in Settings, Theme, "Variables", with its sign, its four numbers in the field, "Used in n places" and the bin. It counts among the 24 and travels with the design's variables file; no table or column. The tab gets no "Add a curve": a curve is made only from an Easing row.
- Written: a list line as today; a custom curve as `cubic-bezier()`; a stored curve as the design's variable with the numbers as its fallback, so a change of the stored curve reaches every element and a removed one keeps moving as it did.
- A drag never changes a stored curve: the row reads "Custom curve" and a line says "Changed from “Soft landing”. The other elements keep it until it is updated." "Update “Soft landing”" writes it back after asking once ("Every element that uses “Soft landing” changes."); "Save as…" keeps a new one.
- Removing a used curve asks: "“Soft landing” is used in 6 places. Each keeps the curve as its own."

## Which keys, what is read out, which measures?
- Tab: the first handle, the second, the numbers, "Play", "Save as…". The arrows move a handle by 0.01, with Shift by 0.1. Enter takes the numbers, or saves the name.
- Read out: "Easing curve"; a handle "First handle, 0.22 across, 1 up", said again after each move; the row "Easing, Soft landing, a stored curve"; "Soft landing saved."
- A drag, an entry of numbers, a save and an update are one undo step each.
- The graph is the panel's width less the row's inset, at most 220 px, always square (so at 240 px too, the buttons wrapping under it). Curve 2.5 px; a handle 12 px, white with a 2 px blue edge, a soft 22 px ring when held, a 1.5 px ring under the keys; the track 14 px high, the bead 10 px; the sign 18 by 14 px.

Related: docs/memory/designer/designer-options-curve-drawing.md, docs/memory/designer/design-variables-tab.md, docs/memory/designer/designer-options-transition.md
