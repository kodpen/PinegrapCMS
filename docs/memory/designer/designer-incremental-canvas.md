---
name: designer-incremental-canvas
description: How a one-node edit redraws only that node's element on the designer canvas (_sdRenderNode, _sdCanvasRedrawNode), which edits keep the full rebuild, and what the in-place redraw keeps (D2, PR #8048)
---

## What does an edit of one node redraw?
- Only that node's element. The edit path calls `_sdRenderNode(node, render)` or `_sdRenderNode(node, renderCanvas)`; `renderCanvas()` reads the scope once (`_sdCanvasScopeTake()`) before it empties the body and, when allowed, runs `_sdCanvasRedrawNode(node)`: `buildNodeEl()` of the subtree replaces the old wrapper, the rest of the canvas keeps its DOM, scroll position, running animations and videos.
- The panels are still all redrawn by `render()` (that is step D3).
- The scope lasts for that one call (cleared in `finally`): a later render is never limited by an earlier edit.

## Which paths name their node?
- Properties `[data-prop]` fields (select, switch and text), `[data-nodeattr]` fields, popover fields; the Styles panel rows; the Attributes panel's prop-bound fields, attribute values and deletes, class chip remove and `addClassToken()`; the in-place text redraws after a removed child or Escape.
- A prop in `_SD_SCOPE_FULL_PROPS` (`id`, `regionType`, `regionName`, `searchSection`, `partOf`, `_editable`, `_locked`, `tag`) passes `null`: another node or the structure reads it.

## When is it still the full rebuild?
- Every caller that names no node: add, move, delete, duplicate, undo/redo, tab switch, code view, multi-selection edits, the 130-odd plain `render()` calls.
- `_sdCanvasScopeOk()` says no: the root, a node not in the page tree, a page other than the one drawn (`body._sdDrawnPage`), a node inside a shared component or system widget (`_resolveSharedSid() > 0`), a `shared_ref`, a placed part or a node inside one.
- A fresh canvas document (`_sdDrawn` false, `_sdPendingScrollY`, `_sdPendingOpen`), or not exactly one wrapper for the node (`_sdCanvasWrapsOf()`).

## What does the in-place redraw keep?
- A collapse opened by a click (`_syncToggleableShowStateFromDom()` first), the active tabs, the `<details>` opened on the canvas (`_restoreDetailsState(el)` only inside the new element, so the others keep their drawn default), a modal, offcanvas or dropdown open inside the node (`_sdCanvasOpenCapture/Restore` on the element). Body classes and attributes, editor-state classes (grid, outline, motion) are untouched: the body is not rewritten.
- The binders (theme switcher, scroller, fx, tooltips, nav overflow, image stamps) run on the whole document again; each binds only what is not bound yet.

## How is it tested?
- `tests/designer_incremental_canvas_test.php` runs the section between `// ── Incremental canvas` and `// ── end of incremental canvas` under Node with a DOM stand-in, and checks the wiring in `renderCanvas()`, the panels and `undo()`.

Related: docs/memory/designer/designer-performance.md, docs/memory/plans/designer-performance-and-publish.md
