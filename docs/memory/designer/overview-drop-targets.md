---
name: overview-drop-targets
description: Designer Overview drop targets (#10263): the gap between rows as one 14 px target, the middle 14 px as "inside", the level picked by the pointer among legal parents, the label and the refused row
---

## Where does a drop over an Overview row land?
- The top 7 px and the bottom 7 px of a row are the gaps above and below it; with the next row's 7 px the gap is one 14 px target. The middle 14 px of a 28 px row is "inside" and frames the row (`.sd-tree-drop-inside`).
- A gap is drawn as a 2 px accent line with an 8 px dot (`.sd-tree-drop-before` / `-after`, `::after`), starting at the chosen level's indent (`--sd-drop-ind`). Colours are `--sd-*` tokens only.
- Before (#10263) the bands were 28 % and 72 % of the row, and a refused middle fell back to "after". Now the zone is strict: a zone with no legal target refuses.

## How is the level picked?
- A gap has its targets read from the rows as drawn (`_sdTreeDropGap()`), deepest first: below a row whose branch is open, only the branch's first place; below a branch's last row, after that row and after each branch that ends there, up to the next row. The gap above a row is the gap below the row drawn before it.
- Each target's dot sits 8 px after its row's indent; the pointer picks the nearest legal one, so 12 px of horizontal travel is one level, the deeper on a tie. `canDrop()` stays the judge of every target; a tree move also refuses a drop beside itself, into itself and outside the editable areas (`_sdMayEdit()`).
- A folded chain's line (`li._sdChainFirst`) counts as its first hidden level.

## What does the label say?
- `#sd-tree-drop-label` follows the pointer (14 px right, 16 px below), hidden on `dragend`, `drop` and when the pointer leaves the tree: "Into {parent}, place {n}" (the place counts the parent's children without the moved ones; the page body is `<body>`).
- A refused row is dimmed (`.sd-tree-drop-refused`), the dragover leaves the default so the cursor says "not allowed", and the label says why: itself, already here, locked, outside the editable areas, holds no other elements, a form in a form, a list takes only list items, or "{parent} does not take this element" (`_sdTreeDropCode()` guesses the reason after `canDrop()` refused).

## Which functions are pure, and where is the test?
- `_sdTreeDropPick(p)` (zone, target, line indent, words) and `_sdTreeDropWhy(code, name)` in `style_designer.src.js`, run under Node by `tests/designer_overview_drop_test.php`: the 7 px reach, the middle 14 px, a level only among legal parents, a refused target's reason.
- The targets of one row and zone are asked once per drag (`_sdTreeDropMemo`), not on every pointer move.
- Long drags (folding, opening after 600 ms, scroll zones, Esc) and moving without dragging are later steps of the plan.
