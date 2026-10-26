---
name: designer-interactions-panel
description: The Interactions section of the designer's properties panel: which nodes get it, its rows, _sdFxSet(), undo and redraws, the code view handing data-pg-fx back to props._fx.
---

## Where is the section, and on which nodes?
- The Interactions section of the properties panel (`propsInteractions()`, after Search and SERP), only on nodes `_sdFxTakes()` accepts: semantic and grid nodes and the content types whose markup carries the node's attributes (designer-node-attributes.md). Rows follow the effect and the trigger (distance only for slides, stagger for load/view, Every time for view, Top offset for sticky, Speed for parallax). `_sdFxSet()` writes one field and normalizes, so switching the effect keeps the timing and drops what no longer applies; None deletes `_fx`.

## How are undo and redraws handled?
- A choice is one undo step and redraws the panel; a typed number is one undo step per focus (`_sdUndoBeforeFirstInput()`) and redraws the panel on leaving it, showing the clamped value.

## What does the code view do with the attributes?
- The code view hands `data-pg-fx*` edits back to `_fx` (`_sdCodeSetFxAttr()`, beside `_sdCodeSetTitleAttr()`); a `data-pg-fx-*` typed on a node with no interaction stays in `_attrs`.
- Tests: `test_designer_fx_panel` in `tests/designer_fx_test.php`, `test_designer_code_view_interaction` in `tests/designer_code_view_attrs_test.php`.

Related: docs/memory/designer/designer-interactions.md
