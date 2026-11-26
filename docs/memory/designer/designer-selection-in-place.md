---
name: designer-selection-in-place
description: A selection marks the HTML tree in place, draws Attributes and Styles after the frame and leaves the icon picker unbuilt until it opens (issue #11795)
---

## What does a selection draw?
- `renderSelectionOnly()` marks the canvas and draws the Options panel and status bar in the click's own work; the Layers tree (`_sdTreeSelectInPlace()`) and the HTML tree (`_sdHtmlTreeSelectInPlace()`) move their marks in place; Attributes and Styles follow after the frame (`_sdSelPanesSoon()`).
- Symptom fixed: with the bottom panel open on a page of some 150 elements, every click redrew about 3,000 panel elements (the whole HTML tree each time) in one task of 325 to 425 ms.

## When is the HTML tree drawn whole after a selection?
- `renderHtmlTree()` records the page and the edit epoch it drew from (`panel._sdHtOf`, `panel._sdHtEpoch`; `_sdEditEpoch` moves with every `saveState()` and `render()`). The in-place mark needs both current, the panel not stale (`_sdPanelStale.html`), the code view off, every chevron matching its node's `_expanded` (a pick on the canvas opens the ancestors) and a row for the node; otherwise the full draw runs.
- The mark goes on the node's opening row (not the closing one), or on the `<html>`/`<body>` rows (`data-ht-root`) for the root, and the row is scrolled into view.

## How are Attributes and Styles put after the frame?
- `_sdSelPanesSoon()` asks for one animation frame and then a timeout, so the panes draw after the selection is painted; selections before it share the one draw, which reads the selection at that moment.
- Capture listeners on `document` (pointerdown, mousedown, keydown, focusin) draw a waiting pane first when the event lands in `#sd-bp-attrs` or `#sd-bp-styles`; an event whose field the draw removed is dropped, so it never edits the element selected before.

## When is the icon picker built?
- `openIconModal()` builds `#sdIconModal` (`initIconModal()`, some 4,000 elements) the first time it opens; the editor's start no longer builds it.

## How is it tested?
- `tests/designer_panels_on_demand_test.php` (`test_designer_selection_*`): the in-place mark and its fallbacks, one draw of the panes after the frame and the guard, the wiring. Timing is checked on dev, not in a test.

Related: docs/memory/designer/designer-panels-on-demand.md, docs/memory/designer/designer-performance.md
