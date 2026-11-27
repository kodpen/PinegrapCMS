---
name: designer-narrow-screen-bars
description: Designer toolbars hide labels, never controls: the page bar at 1024px and on a phone, the canvas bar by its own width in fold levels (DP22), how to measure off-screen controls
---

## What happens to the page toolbar at 1024px and below?
- Toolbars hide the **label**, not the control. Under `@media (max-width: 1024px)` Add Page (`.sd-tabs-add-txt`), Preview and Publish lose their words; each stays an icon with its name in `title`. At 1024px the columns are 190 and 240px, and board F6 of `designer-frame-tidy-drawing.md` draws the bar's three buttons as icons there (FR4, #12031). The Assistant's word goes below 1280px.
- Under 576px (FR4, #12031): no lines around Undo and Redo, 3px between the bar's controls and 8px at its ends, so the open page's tab keeps more of its name; the status bar's Help is its icon (its tip says "Help (?)").

## How does the canvas bar (.sd-bp-bar) give way?
- By its **own width**, not the window's, in a fixed order (DP22): `_sdCanvasBarFold(avail, needs)` picks `data-fold` on `#sd-bp-bar`: 0 everything, 1 hides the width text (`#sd-bp-label`), 2 folds the six sizes into one list (`#sd-bp-size-select`), 3 hides the labels (`.sd-vb-txt`) and puts 12px between zones instead of their lines. Narrower still, the zones wrap to a second row; a control is never hidden.
- `needs` is measured by `_sdCanvasBarMeasure()` (each level set in turn, one layout pass, no paint) and measured again when what the bar holds changes (a MutationObserver: an e-mail design, the badge); a ResizeObserver only picks the level again. Tested in `tests/designer_canvas_bar_test.php`.
- Why it gives way at all: at a 1440px window with both columns open the canvas column is 930px; before #10039 Preview and Open on Site sat under the Options column there (a click hit its header; issue #10034). Both are rows of the Preview menu beside Publish now (`designer-preview-menu.md`).
- The bar is no CSS container any more, and its View menu (`#sd-vb-view-menu`) lives on `pgUI.overlayHost()`, not inside the bar: a menu inside a wrapping, folding bar would be cut by the canvas column.
- In Prototype mode (#10507) the same levels apply to its zones: level 1 hides the map's count instead of the width text (designer/designer-canvas-bar-prototype.md).
- Phones and small touch tablets hide the sizes, the list and the width text (the canvas fills the screen there; `createCanvasIframe()` resets to full width under the same query).

## What are the rules?
- **Never remove a control with `display:none`.** The page bar is `overflow:visible` (the Add Page dropdown needs it), so overflow is clipped, not scrollable. Make room: label to icon, less padding, wrap if needed. If Save or Undo vanishes on a narrow screen, that is a bug.
- **A new bar button puts its label in a separate `<span>`** (the `sd-vb-txt` pattern) and gets a `title`; otherwise it cannot become an icon when the bar is narrow.
- **The measure is an element running out of its column, not `scrollWidth > clientWidth`** alone. In an `overflow:visible` container scrollWidth may not show overflow. Check controls one by one with `getBoundingClientRect().right` against the column's right edge, and `elementFromPoint()` at the button's centre for clickability. The designer's guided tour veil covers the page in a fresh sandbox: remove `.pg-tour-veil` before a hit test.

## Why does a labelled bar button with .sd-icon-btn come out 32 px wide?
- `.sd-icon-btn` (late in `style_designer.css`) sets `width: 32px` and `font-size: 1rem`. A button's own rule of the same weight placed earlier in the file loses, so its label runs out of the 32 px box over the next control.
- Write the own rule as `.sd-icon-btn.<class>` (or place it after); `.sd-icon-btn.sd-tabs-all-btn` and `.sd-icon-btn.sd-tab-suggest-btn` do (#11965: "Suggested groups" was written over the controls after it). `tests/designer_frame_bar_test.php` checks both.
