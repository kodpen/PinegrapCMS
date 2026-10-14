---
name: bootstrap-direct-child-canvas
description: Bootstrap direct-child selectors break on the canvas because of .sd-wrap: two-part fix (display:contents + re-applied rule) and the td display:flex ban.
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

- In (2) do NOT copy Bootstrap's size rules (`width`/`max-width`/`flex`): `X > .sd-wrap > *` is more specific than `.col-md-6` and copying would override column widths. Carry only padding/margin.
- NEVER give a `<td>`/`<th>` `display:flex`: the cell leaves table layout and `colspan` is cancelled. Put the flexbox on a `<div>` inside the cell.

Related: docs/memory/designer/designer-canvas-style-designer.md
