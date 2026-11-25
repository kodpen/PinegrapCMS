---
name: designer-options-states-drawing-motion
description: Transition, Transform and a section's "More" as drawn (options-states.html, boards T1, R1, R2, Z1, steps DO11 and DO12): the rows, the look's values, Origin, the outline on the canvas
---

## How is "Transition" drawn (DO11)?
- In "Shadow and effect" under the sub-head "Transition": one list, None, Colours (text, ground, border), Shadow, Size and place (with Transform), All.
- Once something is chosen, "Duration" (a slider with its number in ms, up to 1,000) and "Easing" (the list Interactions has, first "Default") appear under it.
- Until changed they follow the look (`--pg-duration`, `--pg-ease`) and are drawn in grey with the look's value, so a change of the look carries.
- One value for the element: under a state the rows stay open, the sub-head carries the mark "every state", and they change the same value. Their changed dot shows on Normal only, so a state's count never includes them.
- Choosing another state in the State row plays the change on the canvas. Under reduced motion nothing moves, on the canvas and on the site.

## How is "Transform" drawn (DO12)?
- Under the sub-head "Transform", after Transition: "Move" (two fields, X and Y; a length, a bare number is px), "Scale" (a slider from 0.5 to 1.5 with its number), "Rotate" (a slider from −180° to 180° with its number). The sliders start in the middle; a typed number may pass their ends.
- Under the section's "More": "Skew" (X and Y in degrees) and "Origin", nine places in a square, the middle one until changed, with the place's name and per cents beside it ("Top left", "0% · 0%"). The arrows move among the places.
- Every row can differ in a state and shows Normal's value in grey until it does.
- An element whose Interactions effect moves it gets one info line under the rows: "The hover effect Lift in Interactions moves this element too. Both apply, one on top of the other."

## What is a section's "More"?
- The section's one further level: a quiet row at its end, "More" with a chevron, "Less" when open; the rows stand above "Less".
- It opens by itself while a row under it holds a value, so nothing that is set is hidden.
- DO15 and DO16 put their rows under their sections' "More", drawn the same way.

## What does the canvas show for a transform?
- While a Transform row is dragged or holds the focus, a dashed outline marks the space the element keeps in the layout, named "The place it keeps", and the row's value stands as a tip at the element ("Rotate −6°"). Both go when the row is let go.
- The neighbours never move. The selection's frame and tag are drawn where today's canvas draws them for that kind of element; DO12 leaves them as they are.
- With a state chosen the canvas draws that state's transform; the outline is the same.

## Which measures?
- State group 28 high, segments 24 with corner 7, 2 apart, text 11.5 px at 600, as a button group of `designer-chrome-measures.md`. The ring 7 across, 5 after the text.
- Origin: nine cells of 24, corner 9; a place 5, the chosen one 10 with a soft ring. In it the arrows move among the places.

Related: docs/memory/designer/designer-options-states-drawing.md, docs/memory/designer/designer-interactions-part.md, docs/memory/designer/designer-light-and-simple.md
