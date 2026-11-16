---
name: designer-narrow-screen-bars
description: Designer toolbars hide labels, never controls: the page bar below 1024px window, the canvas bar (.sd-bp-bar) by its own width with a container query, and how to measure off-screen controls.
---

## What happens to the page toolbar below 1024px?
- Toolbars hide the **label**, not the control. Under `@media (max-width: 1023.98px)` `.sd-tabs-add-txt` is hidden; the button stays as an icon with its name in `title`.

## How does the canvas bar (.sd-bp-bar) give way?
- By its **own width**, not the window's: the bar is a container (`container: sd-bp-bar / inline-size`) and `@container sd-bp-bar (max-width: 70rem)` hides `.sd-vb-txt` and tightens button padding. The bar and both groups always have `flex-wrap:wrap`, so what still does not fit drops to a second row.
- Why: at a 1440px window with both columns open the canvas column is 930px, the bar needed 1,041px in Turkish, and Preview and Open on Site sat under the Options column (a click hit its header). The old rule only looked at the window width (issue #10034).
- 70rem (1120px) is above the Turkish bar's full width (~1,041px); a longer language between those widths wraps to two rows with labels. Measured after: no overflow at 1024, 1280, 1440, 1700, 1920 windows; at 1024 the bar is two rows (58px).
- The bar holds no dropdown or popover: `container-type` adds layout containment, so the bar is the containing block of a fixed or absolute menu inside it and its own stacking context: the menu would be placed against the bar and could sit under the canvas. Put such a menu on `pgUI.overlayHost()`.

## What are the rules?
- **Never remove a control with `display:none`.** The page bar is `overflow:visible` (the Add Page dropdown needs it), so overflow is clipped, not scrollable. Make room: label to icon, less padding, wrap if needed. If Save or Undo vanishes on a narrow screen, that is a bug.
- **A new bar button puts its label in a separate `<span>`** (the `sd-vb-txt` pattern) and gets a `title`; otherwise it cannot become an icon when the bar is narrow.
- **The measure is an element running out of its column, not `scrollWidth > clientWidth`** alone. In an `overflow:visible` container scrollWidth may not show overflow. Check controls one by one with `getBoundingClientRect().right` against the column's right edge, and `elementFromPoint()` at the button's centre for clickability. The designer's guided tour veil covers the page in a fresh sandbox: remove `.pg-tour-veil` before a hit test.
