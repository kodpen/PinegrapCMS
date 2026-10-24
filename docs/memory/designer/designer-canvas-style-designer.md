---
name: designer-canvas-style-designer
description: style_designer.js canvas rules: widget tree root, lockstep cfg, table loop areas, the two wrapper models (IFRAME_CSS, measuring, chrome, column padding, margins), express order frame, cycle guard.
---

## What are the canvas pitfalls in style_designer.js?
- `buildSharedRefEl()` renders the system widget's OWN tree (`cached.tree`), not the module-level `tree` variable. When looking up a node's parent use `_sysWidgetRenderTreeRoot || tree`, or the lookup fails silently (this was the real cause of the EO table "overlap" bug).
- `_sysWidgetRenderCfg` and `_sysWidgetRenderTreeRoot` are set/reset in LOCKSTEP.
- A `loop_area` inside `<tbody>/<thead>/<tfoot>` goes through `buildTableLoopAreaEl()` (produces a real `<tr>`; a `<div>` breaks table layout).
- `.sd-loop-area` must have `width:100%`: unwrapped inside `.row` it shrinks as a flex item.

## What are the two wrapper models (2026.4.8)?
- Bundled Bootstrap design: every node sits in a `.sd-wrap` box; Bootstrap 5's direct-child rules are rebuilt with imitation rules marked `_SD_BX` (a leading `\u0001`).
- Other frameworks (`_sdFxFlatOn()`): the wrapper is `display:contents` (`.sd-fx-flat`) and the framework's structural selectors are bridged through the wrapper in the canvas CSSOM by `_sdBridgeSheets()`, specificity unchanged (designer-canvas-selector-bridge.md).
- `IFRAME_CSS` is an array; `_sdIframeCss()` joins it for the model: the `_SD_BX` rules only for the boxed one, marker stripped.
- Measurement-dependent fixes for both models live in `_sdCanvasFidelityPass()`, measured first and written after: `sd-fx-block`, `sd-fx-inline` (block wrapper of an inline element is transparent), loop area's parent layout, card image placeholder (height of the first ghost image), absolutely positioned elements (designer-canvas-absolute-position.md), `sd-fx-fit` (designer-canvas-page-parity.md).
- Measure a wrapper with `_sdBoxRect(w)`: a see-through wrapper's `getBoundingClientRect()` is empty, so it reads its element's box. Its toolbar, note dot and issue badge are positioned against whatever ancestor is positioned, so `_sdPlaceWrapChrome(w)` puts them at the element each time they show.
- No padding on a column wrapper inside a row (`.row > .sd-wrap[data-sd-type="col"]`): it replaced Bootstrap's gutter and every column read wider on the canvas than on the page. Only a column outside a row gets the 4px inset.
- Block content and component wrappers keep `margin:3px 0`; inline-level ones (`sd-wrap-inline`, span/text, icon, link, badge, btn) have none, as their elements on the page. With it, each caption line grew 6px (showcase-floating cards 10-12px taller).

## Why is the express order widget padded on the canvas?
- On the page `_render_system_widget_express_order()` draws the widget inside `.container.py-4.pg-eo-wrap`; the starter's top-level `.row.g-3.pg-eo-grid` (-8px side margins) sits in that padding. The canvas has no such frame: the grid stuck out 6px past the 2px system-wrap border and the canvas scrolled sideways.
- `buildSharedRefEl()` puts the kind on the wrapper (`data-sd-sys-type`) and one `_SD_BX` rule gives `.sd-system-wrap[data-sd-sys-type="express_order"] > .sd-shared-inner` the container's side padding (.75rem). Max-width and `py-4` stay page-only; the page is unchanged. Test: `tests/designer_canvas_widget_frame_test.php`.
- A widget renderer that adds its own frame needs the same canvas mirror.

## How are nested shared components guarded (2026.4.8)?
- Nesting, the three loop checks (`canDrop`, `_validateTree`, `pg_shared_component_cycle()`), `_sdSharedOpen` and `pg_expand_nested_components()`: `shared-component-model.md`.

Related: docs/memory/designer/bootstrap-direct-child-canvas.md, docs/memory/designer/designer-canvas-absolute-position.md
