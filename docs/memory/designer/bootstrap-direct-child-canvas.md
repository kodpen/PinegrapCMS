---
name: bootstrap-direct-child-canvas
description: Bootstrap direct-child selectors break on the canvas because of .sd-wrap: two-part fix (display:contents + re-applied rule), the td display:flex ban, the selected-child lift.
---

## Why do Bootstrap direct-child rules break on the canvas?
- Many Bootstrap rules use direct-child selectors (`.row > *`, `.d-grid > *` ...). On the canvas `.sd-wrap` sits in between, so the rule matches the WRAPPER.
- `display:contents` removes the wrapper's box but NOT from the DOM, so the selector still finds the wrapper; the style goes to an element with no box and evaporates.
- One root cause produced three bugs:
  - Columns glued together (no gap between first/last name): `.row > *` gutter padding.
  - Button narrow in designer, full width on frontend: `.d-grid > *` grid item.
  - Loop band squeezed into the first column and wrapping to 5 lines: `display:flex` on `<td>` cancels `colspan`.

## What is the rule when Bootstrap styles direct children of a container?
The canvas needs BOTH:
1. `X > .sd-wrap { display:contents }`: take the wrapper out of layout.
2. `X > .sd-wrap > * { …the rule itself… }`: re-apply the style to the real child.

- In (2) never copy a size rule (`width`/`max-width`/`flex`) at that specificity: `X > .sd-wrap > *` beats `.col-md-6` and would override column widths. Copy it with zero specificity (`:where(.row > .sd-wrap > …)`) or skip elements with a `col-*` class (the row-cols widths); designer-canvas-page-parity.md.
- A size taken from the parent (`h-100`, an inline `height:100%`) breaks the same way: the wrapper has no height, so the percentage resolves to auto (round panels in a square box collapsed to ovals on the canvas). The canvas passes the height on with `.sd-wrap:has(> .h-100) { height:100% }` (an `_SD_BX` rule in `style_designer.js`); the front end is never changed for it.
- NEVER give a `<td>`/`<th>` `display:flex`: the cell leaves table layout and `colspan` is cancelled. Put the flexbox on a `<div>` inside the cell.

## Why does a selected close button keep its place?
- In the see-through containers (`.alert`, `.card-body`, `.navbar`, `.btn-group` …) the outline of a selected wrapper is drawn on its child, and the child is lifted (`position:relative; z-index:2`) so the outline shows above its neighbours.
- That lift beat Bootstrap's `.alert-dismissible .btn-close { position:absolute }`: on mousedown the X was selected, jumped out of its corner, mouseup landed on the alert and the X never dismissed it on the canvas (alert-info, alert-success-toast, notice-promo).
- The lift is a separate rule now, limited by `_SD_SEL_LIFT`: `.btn-close`, `position-*`, `fixed-*`, `sticky-*` (responsive ones too) and `.stretched-link` are not lifted, nor editor chrome (`_SD_FX_EL`: toolbar, note, issue badge). The outline still applies to every child. Test: `tests/designer_canvas_controls_test.php`.

Related: docs/memory/designer/designer-canvas-style-designer.md
