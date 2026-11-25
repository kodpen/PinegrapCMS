---
name: options-panel-census-layout
description: Options panel census, part 2: gaps to place and size an element (grid tracks, position, layer order, per size values, sticky, scroll snap, aspect ratio, object fit)
---

## Grid tracks and areas?
- Today Display offers Grid (`d-grid`) and Layout's Gap works on it, but no row sets the tracks: `grid-template-columns` and `-rows` are typed in "This element". Missing: a Columns and Rows row (count, equal or fixed sizes, auto-fit with a minimum width), per size. M. Reads `--bs-gutter-x` and the spacing steps for the gap; none for tracks.
- A child cannot span or be placed: `grid-column`, `grid-row`, named areas are code only. Missing: Span columns, Span rows, Start, and named areas drawn as a small grid. M (areas), S (span and start once tracks exist). Reads none.

## Position and layer order?
- Position has its five values and Top, Right, Bottom, Left lengths for all sizes alike; no inset shortcut (fill the parent, centre) and no per size value. Missing: inset presets (S) and offsets per size (M, written as `@media` rules on the element's `pgs-` class). Reads `--bs-breakpoint-*` for the sizes.
- Z Index is a fixed list up to 1080; a typed value needs code. Missing: a typed value beside the list and the Bootstrap layer names (dropdown, sticky, fixed, modal) as choices. S. Reads none: Bootstrap 5.3 keeps its layer numbers in Sass, not in CSS variables, so the list names them.

## Per screen size values?
- Width, Height and their Min and Max, Overflow, Aspect Ratio, Z Index, Position, text Font Size, colours, border, corner, shadow, opacity and Object Fit hold one value for every size. Only the rows in `options-panel-census-today.md` change per size.
- Missing: the size chooser of the Spacing box on those rows, writing `@media (min-width: …)` rules on the element's class in User Styles like the custom style does. M for lengths (one writer serves Width, Height, Position offsets, Font size); S for a row that has a Bootstrap per size class (`w-md-*` does not exist in Bootstrap 5, so Width is M; `overflow-*` has none either). Reads `--bs-breakpoint-*`.
- Hide or show by size: the "Hide on" row (Phone, Tablet, Desktop) since DO3, beside Display None per size (`designer-hide-on-sticky-from.md`).

## Sticky and scroll snapping?
- Sticky is offered twice: Position Sticky (Bootstrap class) and the Interactions effect with Top offset. Position Sticky takes "Sticky from" a size (`sticky-md-top`) since DO3 (`designer-hide-on-sticky-from.md`); a per size top is the Top row by size (DO2).
- Scroll snapping exists only inside the scroller component (`pg-snap-*`, `designer-scroller-wide-slides.md`). A designer cannot make any row of cards snap. Missing: Scroll snap on a flex or grid container (none, mandatory, proximity) and Snap align on its children. S. Reads none.

## Aspect ratio?
- Today a switch with 1:1, 4:3, 16:9, 21:9 or a custom percentage, writing Bootstrap's `ratio` wrapper, which forces its child to fill it. Missing: CSS `aspect-ratio` on any element (a picture, a card) without the wrapper, more presets (3:2, 2:3, 9:16, 4:5) and per size. S (value), M (per size, through the per size writer). Reads none.

## Object fit?
- Today only on a picture: Object Fit (five values) and Object Position as typed text. Missing: the same on video and on a background video. S. Reads none. Object Position is the Focus control, per size, since #11576 (`image-focus-control.md`).
