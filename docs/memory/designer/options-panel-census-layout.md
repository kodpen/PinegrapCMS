---
name: options-panel-census-layout
description: Options panel census, part 2, as built: grid tracks and areas, position presets, per size values, hide and sticky by size, scroll snap, aspect ratio, object fit
---

## Grid tracks and areas?
- Columns and Rows on a grid box (DO4, `designer-grid-columns-rows.md`): a count, equal or fixed sizes, "As many as fit" with a smallest width, per size. Gap stays Layout's Gap.
- A child's "Span columns", "Span rows" and "Start at" (DO5, `designer-grid-child-place.md`); named areas drawn as a small grid, "Area" on the child (DO6, `designer-grid-areas.md`). All per size.

## Position and layer order?
- Position's "Fill the parent" and "Centre" presets of the four offsets; Z Index takes a typed number and offers Bootstrap's layers by name (DO7, `designer-position-presets.md`). Bootstrap 5.3 keeps its layer numbers in Sass, not in CSS variables, so the list names them.
- Top, Right, Bottom, Left take a value per size (DO2).

## Per screen size values?
- Width, Height, their Min and Max, Top, Right, Bottom, Left and Font Size take the Spacing box's size chooser, writing `@media (min-width: …)` rules on the element's class in User Styles (DO2, `designer-size-per-screen.md`). Reads `--bs-breakpoint-*`.
- Still one value for every size: Overflow, colours, border, corner, shadow, opacity (`options-panel-census-today.md` lists the per size rows).
- "Hide on" (Phone, Tablet, Desktop) under Display (DO3, `designer-hide-on-sticky-from.md`).

## Sticky and scroll snapping?
- Position Sticky takes "Sticky from" a size (`sticky-md-top`, DO3); the Interactions effect Sticky with its Top offset stays beside it.
- "Scroll snap" (None, Always, Near) on a flex or grid box and "Snap to" (Start, Centre, End) on its children, one value for all sizes (DO8, `designer-scroll-snap.md`). The scroller component keeps its own `pg-snap-*` (`designer-scroller-wide-slides.md`).

## Aspect ratio and object fit?
- CSS `aspect-ratio` on any element with no wrapper, presets 1:1, 4:3, 3:2, 16:9, 21:9, 2:3, 4:5, 9:16 or typed, per size; today's `ratio` box kept until the ratio is changed (DO9, `designer-aspect-ratio-object-fit.md`).
- A picture and a video (also a background video) have Object Fit; their position is the Focus control, per size (`image-focus-control.md`; a video's needs a poster).
