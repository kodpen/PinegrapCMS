---
name: designer-narrow-screen-bars
description: Designer toolbars below 992px hide labels, never controls: .sd-tabs-add-txt, .sd-vb-txt, flex-wrap on .sd-bp-bar, and how to measure off-screen controls.
---

## What happens to toolbars below 992px?
- Toolbars hide the **label**, not the control. `.sd-tabs-add-txt` and `.sd-vb-txt` are hidden; the button stays as an icon with its name in `title`. The view bar (`.sd-bp-bar`) and its two groups wrap (`flex-wrap:wrap`); overflowing controls drop to a second row.

## What are the rules?
- **Never remove a control with `display:none`.** The page bar is `overflow:visible` (the Add Page dropdown needs it), so overflow is clipped, not scrollable. Make room: label to icon, less padding, wrap if needed. If Save or Undo vanishes on a narrow screen, that is a bug.
- **A new bar button puts its label in a separate `<span>`** (the `sd-vb-txt` pattern) and gets a `title`; otherwise it cannot become an icon on narrow screens.
- **The measure is an element running off the screen, not `scrollWidth > clientWidth`.** In an `overflow:visible` container scrollWidth does not show overflow. Check controls one by one with `getBoundingClientRect().right > innerWidth`.
