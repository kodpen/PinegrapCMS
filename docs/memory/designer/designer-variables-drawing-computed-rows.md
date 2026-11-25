---
name: designer-variables-drawing-computed-rows
description: The computed rows a bound control's list offers in Options (boards B6 and B7 of the variables drawing, DV3): the type scale's steps and the base unit's multiples, their names, the chip, the order
---

## Which computed rows does the list offer, and why?
- Architect (2026-11-24): DV3 offers them, since scale, base unit and section rhythm write only their own token and without these rows have no use in Options.
- A size control, under "Type scale": "XS", "S", "L", "XL", "2XL", "3XL", "4XL", "5XL", each with the size it gives now in whole pixels; the base is the row "Base size". A spacing control, under "Units": "2 units", "3 units", "4 units", "6 units", "8 units".
- They are not variables: no "Add", no rename, no row on the Variables tab. A bound control shows the same chip ("Scale 2XL", "6 units") with "Detach"; a change of base size, scale or base unit redraws every such node.
- Order: "Variables", "Your own", the computed rows, then the typed value; the list scrolls inside the room under the control.

## Where is it drawn?
- `designer/designer-variables-drawing/variables.html`, boards B6 (a size control with "Type scale") and B7 (a spacing control with "Units"); the rest of the list and the chip: `designer-variables-drawing.md`.

## What is the row made of?
- The same 30 px row as a variable's: the group's icon, the name, the value it gives now in grey; the bound one carries the tick. A heading with no rows is left out, and the search field narrows the computed rows too.
- Nothing is added to the Options panel's head or a section's head (`designer-light-and-simple.md`).
