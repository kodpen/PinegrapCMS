---
name: designer-narrow-screen-bars
description: Designer toolbars hide labels, never controls: the page bar below 1024px window, the canvas bar (.sd-bp-bar) by its own width in fold levels (DP22), and how to measure off-screen controls.
---

## What happens to the page toolbar below 1024px?
- Toolbars hide the **label**, not the control. Under `@media (max-width: 1023.98px)` `.sd-tabs-add-txt` is hidden; the button stays as an icon with its name in `title`.

## How does the canvas bar (.sd-bp-bar) give way?
- By its **own width**, not the window's, in a fixed order (DP22): `_sdCanvasBarFold(avail, needs)` picks `data-fold` on `#sd-bp-bar`: 0 everything, 1 hides the width text (`#sd-bp-label`), 2 folds the six sizes into one list (`#sd-bp-size-select`), 3 hides the labels (`.sd-vb-txt`) and puts 12px between zones instead of their lines. Narrower still, the zones wrap to a second row; a control is never hidden.
- `needs` is measured by `_sdCanvasBarMeasure()` (each level set in turn, one layout pass, no paint) and measured again when what the bar holds changes (a MutationObserver: an e-mail design, Open on Site shown, the badge); a ResizeObserver only picks the level again. Tested in `tests/designer_canvas_bar_test.php`.
- Why it gives way at all: at a 1440px window with both columns open the canvas column is 930px; before #10039 Preview and Open on Site sat under the Options column there (a click hit its header; issue #10034).
- The bar is no CSS container any more, and its View menu (`#sd-vb-view-menu`) lives on `pgUI.overlayHost()`, not inside the bar: a menu inside a wrapping, folding bar would be cut by the canvas column.
- Phones and small touch tablets hide the sizes, the list and the width text (the canvas fills the screen there; `createCanvasIframe()` resets to full width under the same query).

## What are the rules?
- **Never remove a control with `display:none`.** The page bar is `overflow:visible` (the Add Page dropdown needs it), so overflow is clipped, not scrollable. Make room: label to icon, less padding, wrap if needed. If Save or Undo vanishes on a narrow screen, that is a bug.
- **A new bar button puts its label in a separate `<span>`** (the `sd-vb-txt` pattern) and gets a `title`; otherwise it cannot become an icon when the bar is narrow.
- **The measure is an element running out of its column, not `scrollWidth > clientWidth`** alone. In an `overflow:visible` container scrollWidth may not show overflow. Check controls one by one with `getBoundingClientRect().right` against the column's right edge, and `elementFromPoint()` at the button's centre for clickability. The designer's guided tour veil covers the page in a fresh sandbox: remove `.pg-tour-veil` before a hit test.
