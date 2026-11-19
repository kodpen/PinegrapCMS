---
name: designer-tree-move-keeps-selection
description: Dragging rows in the designer's Overview tree: which picked rows move (#10169) and the moved elements stay selected, as on the canvas (#10067).
---

## What is selected after rows are dragged in the Overview tree?
- The moved elements, the same as after a move on the canvas (`handleDrop()` selects the dropped node). The tree's drop handler in `buildTreeItems()` ends both tree-to-tree moves (among rows, and into a shared component's tree) with `_sdTreeMoveSelect(nodesToMove)`.
- One row dragged is a plain selection, even when another element was selected before the drag.
- A multi-selection moved together stays selected as a whole, with the same primary; when the primary stayed behind (dropped on its own row), the last moved element becomes primary.
- A palette item dropped on the tree goes through `handleDrop()` and selects the new element, as before.

## Which picked rows does a tree drag move?
- `_sdTreeMoveSet(picked, target)`: the dragged row, or its multi-selection, without the target, the root and repeats. A picked element inside another picked one travels inside it and is not moved on its own; an element holding the target stays where it is.
- Before (#10169), picking a parent and its child and dragging the child moved both but took only the child out, so the parent was in the page twice (same object, same id). The removal skipped an ancestor of the dragged node while the insert still added it.

## Why does it reset `_sdTreeOpenedFor` / `_sdTreeShownFor`?
- The moved node is often the one already selected, and the Overview does not open or scroll for a selection it already showed (`designer-overview-follows-selection.md`). Clearing both makes the redraw open the branches over the new place and bring the row into view.

## What was the symptom before?
- The handler ended with `selectedNodes = []; selectedNode = null; render();`: after the move nothing was selected, no active row, the Options panel empty; on a long page the row had to be found again (#10067).

## Where is it tested?
- `tests/designer_tree_move_selection_test.php` runs the pure `_sdTreeMoveSelection()` under Node and checks that both move endings in `buildTreeItems()` call `_sdTreeMoveSelect()`; `tests/designer_tree_move_set_test.php` runs `_sdTreeMoveSet()`.
