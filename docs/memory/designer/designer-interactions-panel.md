---
name: designer-interactions-panel
description: Interactions section of the properties panel and on the canvas: nodes, rows, _sdFxSet(), undo, canvas binding, Play button, code view back to props._fx.
---

## Where is the section, and on which nodes?
- The Interactions section of the properties panel (`propsInteractions()`, after Search and SERP), only on nodes `_sdFxTakes()` accepts: semantic and grid nodes and the content types whose markup carries the node's attributes (designer-node-attributes.md). Rows follow the effect and the trigger (distance only for slides, stagger for load/view, Every time for view, Top offset for sticky, Speed for parallax). `_sdFxSet()` writes one field and normalizes, so switching the effect keeps the timing and drops what no longer applies; None deletes `_fx`.

## How are undo and redraws handled?
- A choice is one undo step and redraws the panel; a typed number is one undo step per focus (`_sdUndoBeforeFirstInput()`) and redraws the panel on leaving it, showing the clamped value.

## How does the canvas show it?
- `_sdApplyNodeAttrs()` writes the `_sdFxAttrs()` attributes on the canvas element (a panel copy of the names gives way). `_sdFxCanvas()`, after `_sdScrollers()` in every render, loads `pg_fx.min.js` into the frame once (`#pg-fx-js`, `pgFxEditing = true`) and calls `pgFxBind(canvasDoc)` after later renders.
- With `pgFxEditing` only the first bind plays entrances; a node drawn again by a later render shows in place (`pg-fx-armed`, no start state), so an edit does not replay the page. `bind()` forgets records of nodes no longer in the document. A paused canvas answers reduced motion (`_sdMotionShimFn()`), so nothing moves; the Animations button resets the frame and the entrances play once.
- "Play on the canvas" (rows with a trigger) calls `_sdFxReplay(node)` → `pgFx.replay(el)`: the transitions are set `important`, so they beat `body.sd-motion-paused`; afterwards the node's own transition comes back. Sticky gets `position: sticky` from the script where no head style gave it.
- Checked in Chromium on a static page (I3): load and view entrances, hover, replay under the paused rule. The designer itself was not run.

## What does the code view do with the attributes?
- The code view hands `data-pg-fx*` edits back to `_fx` (`_sdCodeSetFxAttr()`, beside `_sdCodeSetTitleAttr()`); a `data-pg-fx-*` typed on a node with no interaction stays in `_attrs`.
- Tests: `test_designer_fx_panel` in `tests/designer_fx_test.php`, `test_designer_code_view_interaction` in `tests/designer_code_view_attrs_test.php`.

Related: docs/memory/designer/designer-interactions.md
