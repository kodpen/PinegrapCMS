---
name: overview-move-without-drag
description: Designer Overview, moving without dragging (DP16): row buttons, keys on a focused row, Ctrl+X / Ctrl+V, the "Move…" window part, one undo step, a refused move said in words
---

## Where can an element be moved without dragging?
- Row buttons: up, down and "Move…" (`.sd-tree-mv`), one group the tree moves to the row under the pointer, else the selected row; only a row that can be dragged gets them (`_sdTreeMvPlace()`). Up and down are Alt+Up and Alt+Down.
- The "Move…" window: from the row buttons, the Overview row's right-click menu ("Move…"), the selected element's canvas toolbar (`.sd-tb-move-btn`) and the M key (`_sdMoveKey()`, in both shortcut listeners; M had no handler before). It moves the selection; a multi-selection moves together.

## Which keys work on a focused Overview row?
- `_sdTreeRowKey(e)`, bound once on `#sd-tree-list` (`_sdTreeRowKeydown()`): Up and Down go to the row above or below and select it; Left closes an open row, else goes to the parent row; Right opens a closed row, else goes to its first child.
- Alt+Up / Alt+Down: before the sibling above, after the one below. Alt+Left: after its parent (refused at the top of the page or of a component's tree). Alt+Right: last inside the sibling above (`_sdTreeKeyPlan()`).
- Ctrl+X dims the rows (`.sd-tree-cut`); Ctrl+V on another row puts them after it; Esc drops the cut, Ctrl+C too. Without a cut Ctrl+V is the editor's paste as before.
- The selected row is the tree's one tab stop (`tabIndex` 0, the rest -1); after a key move its row keeps the focus (`_sdTreeFocusSel()`).

## How is a move judged and done?
- `_sdMoveCheck(nodes, ref, pos)` asks what a drop asks (`overview-drop-targets.md`): locked, outside the editable areas (`_sdCanEditNode()`, `_sdMayEdit()`), into itself, already there, then `canDrop()` for each moved element; into a shared component is into its cached tree. Its `why` is `_sdTreeDropWhy()`'s words; nothing goes before or after `<body>`.
- `_sdMoveNodes()`: refused, a warning toast says why and nothing changes; else one `saveState()`, the move, `_sdTreeMoveSelect()` (moved elements selected, their rows opened and shown) and `scrollCanvasToNode()`. Drags already were one undo step.

## What does the "Move…" window draw?
- The part `move` (`assets/js/style_designer_move.js`, `pg_designer_parts()`), loaded at the first open; it reads the page through `_sdMoveHost()` (the tree search's `kids` and `fields`) and takes only `_sdT` and `esc` from the kit.
- Head: the move icon, "Where should {name} move?" on up to two lines, the ×. The search ("Search by name…") is focused; targets are the body and every element but the moved ones and their insides, each with its path (past four steps: the section, "…", the last two), the match marked, at most 80 drawn.
- Turkish letters are folded by `_mvFold()`, the part's own copy beside the tree search's `_tsFold()`; the plan's close-out merges the folds.
- Before, Into, After (After first; Into for the body), the refusal's words live under them, "Move" disabled while refused. Enter picks the active result, a second Enter moves.
- Under 576 px the window is the bottom sheet of `designer-panels-measures-6.md`: from 58 px under the top, 20 px corners, a 38 by 4 handle that closes when pulled 60 px down, 54 px results, 44 px place group and button.

## Where is the test?
- `tests/designer_overview_move_test.php`: each row key, each key's plan, the key moves through `canDrop()` with one undo step and the element selected and shown, refusals that change nothing and say why, the window's moves and search, and where the window opens from.
