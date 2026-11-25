---
name: options-panel-census-layout
description: Options panel census, part 2: gaps to place and size an element (grid tracks, position, layer order, per size values, sticky, scroll snap, aspect ratio, object fit)
---

## Grid tracks and areas?
- Columns and Rows on a grid box are built (DO4, `designer-grid-columns-rows.md`): a count, equal or fixed sizes, "As many as fit" with a smallest width, per size. Gap is Layout's Gap.
- A child spans and is placed by "Span columns", "Span rows" and "Start at" (DO5 #11654, `designer-grid-child-place.md`). Missing: named areas drawn as a small grid (DO6). M. Reads none.

## Position and layer order?
- Position has its five values and Top, Right, Bottom, Left lengths for all sizes alike; no inset shortcut (fill the parent, centre) and no per size value. Missing: inset presets (S) and offsets per size (M, written as `@media` rules on the element's `pgs-` class). Reads `--bs-breakpoint-*` for the sizes.
- Z Index is a fixed list up to 1080; a typed value needs code. Missing: a typed value beside the list and the Bootstrap layer names (dropdown, sticky, fixed, modal) as choices. S. Reads none: Bootstrap 5.3 keeps its layer numbers in Sass, not in CSS variables, so the list names them.

## Per screen size values?
- Width, Height and their Min and Max, Overflow, Z Index, Position, text Font Size, colours, border, corner, shadow, opacity and Object Fit hold one value for every size. Only the rows in `options-panel-census-today.md` change per size.
- Missing: the size chooser of the Spacing box on those rows, writing `@media (min-width: …)` rules on the element's class in User Styles like the custom style does. M for lengths (one writer serves Width, Height, Position offsets, Font size); S for a row that has a Bootstrap per size class (`w-md-*` does not exist in Bootstrap 5, so Width is M; `overflow-*` has none either). Reads `--bs-breakpoint-*`.
- Hide or show by size: the "Hide on" row (Phone, Tablet, Desktop) since DO3, beside Display None per size (`designer-hide-on-sticky-from.md`).

## Sticky and scroll snapping?
- Sticky is offered twice: Position Sticky (Bootstrap class) and the Interactions effect with Top offset. Position Sticky takes "Sticky from" a size (`sticky-md-top`) since DO3 (`designer-hide-on-sticky-from.md`); a per size top is the Top row by size (DO2).
- Scroll snapping exists only inside the scroller component (`pg-snap-*`, `designer-scroller-wide-slides.md`). A designer cannot make any row of cards snap. Missing: Scroll snap on a flex or grid container (none, mandatory, proximity) and Snap align on its children. S. Reads none.

## Aspect ratio?
- Built (DO9): CSS `aspect-ratio` on any element with no wrapper, presets 1:1, 4:3, 3:2, 16:9, 21:9, 2:3, 4:5, 9:16 or typed, per size; today's `ratio` box kept until the ratio is changed (`designer-aspect-ratio-object-fit.md`).

## Object fit?
- A picture and a video (also a background video) have Object Fit (five values); their position is the Focus control, per size (`image-focus-control.md`; a video's needs a poster, `designer-aspect-ratio-object-fit.md`).
