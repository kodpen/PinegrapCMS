---
name: designer-select-similar-drawing
description: The drawing of "Select similar" (sheet DD13, step SE2): boards E1 to E4, where the row sits, the line above the Options panel with its two lists, what it settles, the new texts and the key
---

## Where is the drawing?
- `designer/designer-select-similar-drawing/select-similar.html` with `drawing.css`, on `ui/look-sheet/sheet.css`; pictures `select-similar-dark.jpg`, `select-similar-light.jpg`. Built as drawn.

## Which board belongs to step SE2?
- E1 the row in the canvas menu, an Overview row’s menu and the command palette; the new row of Keyboard shortcuts.
- E2 the press on one of twelve cards: the canvas, the Options panel with the line, the first-use line.
- E3 the two lists, "One of its classes" with its second list, the list of a placed shared component.
- E4 one taken out, what is left out, nothing found, the panel at 240 px.

## Where does the row sit?
- Canvas menu: under "Select Parent". Overview row's menu: first in the group of "Move…", "Duplicate", "Delete". Command palette: only while one element is chosen, its kind at the right. Icon `bi-ui-checks-grid`, key S at the row's end.
- Only for one chosen element that is not the page; with several chosen there is no row and the key does nothing. No row in the Options panel's three-dot menu, no button in a bar. Nothing is counted before the press.

## What is the line?
- The first thing in the Options panel, above the identity row, 36 px: the count in bold, then the two lists as words with a small arrow: "12 similar · same kind and classes · in this section". Under it the several-elements panel as it is (`designer-options-multiple.md`).
- It stays while the selection is what the line found, and goes when the selection is cleared or another element is clicked. Choosing is not an undo step.
- Not fitting one row: the count keeps the first row, the lists go under it, side by side or one under the other; a dot only between parts sharing a row.

## What do the lists hold?
- Alike by: "Same kind and classes" (start), "Same kind", "One of its classes" (a second list of the element's classes; no row without a class, no second list with one class), "The same shared component" only for a placed shared component: the matcher's `ways()` in its order.
- Where: "Beside it", "In this section" (start), "On this page".
- A row's number is what it would choose, the chosen one counted, the other list unchanged; a row that finds no other reads "none", dimmed, not choosable. Counted when a list opens. Nothing is remembered between presses.
- The line's words: "same kind and classes", "same kind", "class card", "same shared component"; "beside it", "in this section", "on this page".

## What does the sheet settle beyond the plan?
- Ctrl and a click on a chosen one: "11 of 12 similar". On an element the line did not find: an ordinary selection, the line goes.
- Left out, as a second row: "3 left out: 1 you may not edit, 2 inside a shared component." (third reason "a system widget's own parts"), in that order, zero ones not named; never in a count.
- Nothing found: the element stays chosen with its own panel; "No other element like this one here", the two lists, and a button "Look in this section" or "Look on this page". Nothing on the page: "No other element like this one on this page", no button.
- First use, once, under the line: "Every element like the one you chose is selected. Change something once and all of them change." with "Got it"; gone after the first change too.

## Which keys, and what is read out?
- S with one element chosen and no field in use. The two words are the panel's first two Tab stops; Enter or Space opens, arrows move, Enter chooses, Escape closes the list and keeps the selection.
- Read out after the press and each choice: "12 similar elements selected: same kind and classes, in this section." A list's row with its count: "Same kind, 13".
- Keyboard shortcuts: "Select the elements like the selected one".

Related: docs/memory/designer/designer-select-similar-matcher.md
