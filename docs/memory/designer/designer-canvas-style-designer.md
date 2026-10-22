---
name: designer-canvas-style-designer
description: style_designer.js canvas rules: widget tree root, lockstep cfg, table loop areas, .sd-loop-area width, two wrapper models, absolutely positioned elements, shared component cycle guard.
---

## What are the canvas pitfalls in style_designer.js?
- `buildSharedRefEl()` renders the system widget's OWN tree (`cached.tree`), not the module-level `tree` variable. When looking up a node's parent use `_sysWidgetRenderTreeRoot || tree`, or the lookup fails silently (this was the real cause of the EO table "overlap" bug).
- `_sysWidgetRenderCfg` and `_sysWidgetRenderTreeRoot` are set/reset in LOCKSTEP.
- A `loop_area` inside `<tbody>/<thead>/<tfoot>` goes through `buildTableLoopAreaEl()` (produces a real `<tr>`; a `<div>` breaks table layout).
- `.sd-loop-area` must have `width:100%`: unwrapped inside `.row` it shrinks as a flex item.

## What are the two wrapper models (2026.4.8)?
- Bundled Bootstrap design: every node sits in a `.sd-wrap` box; Bootstrap 5's direct-child rules are rebuilt with imitation rules marked `_SD_BX`.
- Other frameworks (`_sdFxFlatOn()`): the wrapper is `display:contents` (`.sd-fx-flat`) and the framework's structural selectors are bridged through the wrapper in the canvas CSSOM by `_sdBridgeSheets()` (specificity unchanged).
- Measurement-dependent fixes for both models live in `_sdCanvasFidelityPass()`: `sd-fx-block`, `sd-fx-inline` (block wrapper of an inline element is transparent), loop area's parent layout, card image placeholder (height of the first ghost image).
- Measure the wrapper with `_sdBoxRect()`.

## Where does the canvas put an absolutely positioned element?
- Where the page does. Every boxed wrapper is `position:relative`, so such an element (hero overlay, card or badge over a picture, `pg-float`) used to be placed against its own wrapper or a wrapper between it and its frame.
- `_sdCanvasFidelityPass()` (boxed model only) measures each wrapper's element: computed `position:absolute`, shown, not clipped to nothing. Its wrapper gets `sd-fx-abs` + `sd-fx-flat` (see-through); the wrappers up to the first positioned page element get `sd-fx-unpos` (`position:static`, `min-height:0`). The root wrapper stays positioned. Measured, not by class, so a media-query rule (`pg-float` from 992 px) is followed on resize.
- Clipped elements (`visually-hidden`, `.btn-check` input) are left alone: they show nowhere and their wrapper box is the only handle.
- `_sdPlaceWrapChrome()` places the toolbar, note dot and issue badge of see-through and of `sd-fx-unpos` wrappers by script.
- Side effect, as on the page: a full-size overlay (`hero-image-bg`) is now what a click on the section background hits; pick the section from the layers tree.

## How are nested shared components guarded (2026.4.8)?
- Code walking component trees is guarded against cycles by `_sdSharedOpen`.
- A cyclic save is rejected in three places: `canDrop`, `_validateTree`, `pg_shared_component_cycle()`.
- Remaining markers in widget output are expanded by `pg_expand_nested_components()`.

Related: docs/memory/designer/bootstrap-direct-child-canvas.md
