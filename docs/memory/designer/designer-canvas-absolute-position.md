---
name: designer-canvas-absolute-position
description: Where the designer canvas places an absolutely positioned element (sd-fx-abs, sd-fx-unpos), which wrappers stop the walk, and how the chrome of see-through wrappers is placed.
---

## Where does the canvas put an absolutely positioned element?
- Where the page does. Every boxed wrapper is `position:relative`, so such an element (hero overlay, card or badge over a picture, `pg-float`) used to be placed against its own wrapper or a wrapper between it and its frame.
- `_sdCanvasFidelityPass()` (boxed model only) measures each wrapper's element: computed `position:absolute`, shown, not clipped to nothing. Its wrapper gets `sd-fx-abs` + `sd-fx-flat` (see-through); the wrappers up to the first positioned page element get `sd-fx-unpos` (`position:static`, `min-height:0`). The root wrapper stays positioned. Measured, not by class, so a media-query rule (`pg-float` from 992 px) is followed on resize.
- Clipped elements (`visually-hidden`, `.btn-check` input) are left alone: they show nowhere and their wrapper box is the only handle.

## How is the chrome of those wrappers placed?
- The toolbar, note dot and issue badge of see-through and `sd-fx-unpos` wrappers are placed by script (`_sdPlaceChromeOf()`, all measured, then written): on hover, after each fidelity pass (`_sdPlaceSelectedChrome()`) and when the debounced validation puts new badges on (`applyIssueBadges()`). Without the last, a badge sat at the frame's corner until hovered.

## Where does the walk stop?
- At a bound-off (`sd-binding-off`, its "Off" label is an `::after`) or a shared/system widget wrapper (`sd-shared-wrap`, its name band): CSS places those marks against the wrapper, so it stays positioned and places the element, as before the pass. Inside a widget the frame is usually a page element below that wrapper anyway.
- Side effect, as on the page: a full-size overlay (`hero-image-bg`) is now what a click on the section background hits; pick the section from the layers tree.

Related: docs/memory/designer/designer-canvas-style-designer.md
