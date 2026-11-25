---
name: designer-options-census-steps
description: Plan designer-options-census, DO2 to DO9: a value per screen size, grid columns, rows, spans and areas, the small layout rows (inset presets, layer order, scroll snap, aspect ratio, object fit)
priority: 7
---

## What holds for these steps?
- The frame of `designer-options-census.md`: where a row sits, what every option keeps, the lines. The gaps as counted: `designer/options-panel-census-layout.md`.

## Which steps set a value per screen size and a grid? (line "sizes and grid")
- DO2 (M) Done (#11571). The per size writer and its chooser: the Spacing box's size chooser on Width, Height, their Min and Max, Top, Right, Bottom, Left and Font Size.
  - A length for a size is an `@media (min-width: …)` rule on the element's class in User Styles, as the custom style writes; a row that has a Bootstrap per size class (`w-md-50`) writes the class.
  - Pure writer and reader with tests; the canvas shows the size the chooser names; a value set for one size shows a dot on the chooser.
- DO3 (S) Done (#11602). "Hide on" with Phone, Tablet and Desktop (Display None per size, today's classes, read back from them), and "Sticky from" a size on Position Sticky (`sticky-md-top`).
- DO4 (M) after DO2: on an element whose Display is Grid, "Columns" and "Rows": a count, equal or fixed sizes, "As many as fit" with a smallest width; per size through DO2's writer. Layout's Gap stays the gap.
- DO5 (S) after DO4: on a grid's child, "Span columns", "Span rows" and "Start at".
- DO6 (M) after DO5 and DD23: named areas drawn as a small grid: cells dragged into an area, a child given its area from a list; per size.

## Which steps add the small layout rows? (line "small rows")
- DO7 (S) Done (#11566). Position: "Fill the parent" and "Centre" as presets of the four offsets; Z Index takes a typed number and offers Bootstrap's layers by name (Dropdown, Sticky, Fixed, Modal).
- DO8 (S) Done (#11587). "Scroll snap" on a flex or grid container (None, Always, Near) and "Snap to" on its children (Start, Centre, End). The scroller component keeps its own (`designer/designer-scroller-wide-slides.md`).
- DO9 (S) after DO8: Aspect Ratio on any element as CSS `aspect-ratio` with no wrapper, adding 3:2, 2:3, 9:16 and 4:5; per size once DO2 has landed. Object Fit on a video and a background video.
  - An element that uses today's ratio wrapper stays as it is until its ratio is changed.
  - A picture's position is the Focus control (`designer/image-focus-overview.md`), never a second picker; a video gets that control too.

## What do these steps read from the look?
- `--bs-breakpoint-*` for the sizes and `--bs-gutter-x` for a grid's first gap. Bootstrap 5.3 keeps its layer numbers in Sass, so DO7's list names them.
