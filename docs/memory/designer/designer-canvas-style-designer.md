---
name: designer-canvas-style-designer
description: style_designer.js canvas rules: widget tree root, lockstep cfg, table loop areas, .sd-loop-area width, two wrapper models, shared component cycle guard.
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

## How are nested shared components guarded (2026.4.8)?
- Code walking component trees is guarded against cycles by `_sdSharedOpen`.
- A cyclic save is rejected in three places: `canDrop`, `_validateTree`, `pg_shared_component_cycle()`.
- Remaining markers in widget output are expanded by `pg_expand_nested_components()`.

Related: docs/memory/designer/bootstrap-direct-child-canvas.md
