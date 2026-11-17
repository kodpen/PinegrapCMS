---
name: designer-undo-keeps-selection
description: Undo and Redo in the designer keep the selected element (found again by _id in the restored tree) and the Options panel's scroll; dropped only when the step removed it.
---

## What happens to the selection on Undo or Redo?
- It is kept. `undo()` and `redo()` take the selection as ids and the Options panel's (`#sd-properties`) scroll before the step (`_sdStepSelectionKeep()`), then `_sdStepReselect()` finds the ids again with `_findNodeAnywhereById()` (main tree and shared component caches) and draws.
- An element the step removed drops out of the selection; when nothing is left, nothing is selected and the Options panel shows "Select an element to edit its properties".
- A multi-selection keeps the elements that remain; when the primary one is gone, the last remaining one becomes primary (as Ctrl+Click does); one left is a plain selection.
- The panel's scroll is put back only when it still shows the same element, once after `render()` and once on the next frame (sections filled after the paint).

## Why by id?
- The step parses a JSON snapshot, so every node is a new object; the snapshot keeps each `_id` (`reindex()` only reads them), so the same id names the same element.

## What was the symptom before?
- Every Undo or Redo cleared the selection: the status bar read "No selection", the Options panel emptied, and trying another value took a click on the element and a scroll back to the section (#10010, PR #10024).

## Where is it tested?
- `tests/designer_undo_selection_test.php` runs the pure `_sdStepSelection()` under Node and checks that `undo()` / `redo()` call the reselect.
