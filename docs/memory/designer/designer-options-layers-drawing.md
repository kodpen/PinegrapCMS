---
name: designer-options-layers-drawing
description: The drawing of background layers, Clip shape and Mask (plan designer-options-census, DO13 and DO14): the sheet, its nine boards, which step builds which, what is new, where it differs from the plan
---

## Where is the drawing?
- `designer/designer-options-layers-drawing/`: `options-layers.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. The designer's own parts are dark in both pictures.
- The sample is the hero section of the page "Home", design "Still Water Yoga": a gradient over a picture over a colour. Names and values are examples; the labels are the steps'.
- The layers as drawn: `designer-options-layers-drawing-layers.md`. Clip shape, Mask, the canvas and the keys: `designer-options-layers-drawing-shape.md`.

## Which board belongs to which step?
- DO13: L1 "Background layers" in Color and background (no layer, a background made before, three layers, "Add layer", eight layers); L2 a gradient layer (Linear with the strip and the chosen stop, the palette list, a third stop, Radial, Conic); L3 a picture layer with its "More", a colour layer, a picture that is gone, a background the list cannot read; L4 a layer dragged, moved with the keys, the list under a state, the panel at 240 px.
- DO14: C1 "Clip shape" (the list, Circle, Rounded inset, Slant, Arrow); C2 "Mask" (the list, Fade, Picture, none chosen yet); C4 shapes on an image.
- Both: C3 the canvas (three layers, one taken away, Slant, Fade). Z1 (DO13): the strip and its stops three times enlarged, the keys and what is read out.

## What does the drawing add to the designer's bars?
- Nothing: no button in the Options panel's head, the page bar or the canvas bar (`designer-light-and-simple.md`). A list in the Style tab's "Color and background", and two rows under a sub-head "Shape" in Shadow and effect's "More", after "Blend".

## Which parts are drawn from what exists, and which are new?
- As today: the identity row, the tabs, the section card with its count, a row with its changed dot, a list with its tick, a field, a slider with its number, the nine places of Origin, "More" / "Less", the State row, the info and warn lines, the quiet small button, the bin, the selection's tag.
- New: a layer's line (grip, a small picture of the layer, kind, summary, bin when open, chevron) and the rows opened under it; the blue line where a dragged layer lands; the gradient's strip on a checked ground with a handle a stop; the link mark on a palette colour; a small picture and the folder button in a picture's field; the shape signs in the two lists; the dashed outline of the whole box on the canvas.

## Where does the drawing differ from the plan's words?
- The plan lists "colour, gradient, picture" layers: the drawing keeps "Background Color" (the theme colour) as its own row above the list, under every layer; a "Colour" layer is a colour of its own with its opacity.
- The plan does not say which rows go: the list takes the place of Custom Background Color, Bg Image, Bg Size, Bg Pos, Bg Repeat and Bg Attach. Text Color, Background Color, Bg Opacity, Gradient and Opacity stay.
- The plan gives no limits: two stops is the least and sixteen the most, eight layers the most.
- The plan says Clip shape and Mask are "shown on the canvas as chosen": the drawing adds the dashed outline of the element's whole box, and one line said once about clicks and shadows.
- The drawing wins where the two differ (`workflow/architect-draws-new-part.md`); what neither says the worker asks of the architect.

Related: docs/memory/designer/options-panel-census.md, docs/memory/designer/options-panel-census-look.md, docs/memory/designer/designer-options-effects.md
