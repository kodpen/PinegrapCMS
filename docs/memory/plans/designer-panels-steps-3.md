---
name: designer-panels-steps-3
description: Plan designer-panels, steps DP11 to DP16 of the Overview chain: rows and sections, folded chains and hints, the tree search and page map, wide drop targets, long drags, moving without dragging
---

## Which steps are there?
- DP11 (M) after DP1: rows, sections, what is open. Done: #10207.
  - Functions: `renderTree()`, `buildTreeItems()`; the head and the sticky path.
  - Tests: only the selection's section open on opening; the path's steps; the label's 150 px with fourteen levels (a pure function for the indent and the depth badge).
  - Pictures: the 500-row page beside `overview-1-long-page.jpg`; a multiple selection.
  - Features: D29 (without the search), D30, D33, D36. Keeps K6.

- DP12 (M) after DP11: folded chains and hints. Done: #10235.
  - New: pure functions for a chain of single-child wrappers and for a row's hint (text, file name, link target), with their tests.
  - Tests: a chain of four folded and opened; a chain that holds the selection opened; 61 rows named alike each with its own hint; a hint cut, never the name.
  - Features: D31, D32.

- DP13 (M) after DP12: the tree search and the page map. Done: #10303.
  - New: a part. The fold function of DP8 when it has landed, else its own copy named in the PR body for the close-out.
  - Tests: name, text, class and id found; a result's path; the map's ranges from the sections' sizes.
  - Features: D34, D35 and the search of D29.

- DP14 (M) after DP11: wide drop targets. Done: #10263.
  - New: a pure function from the pointer, the row's box and the legal parents to (before, inside, after, level) and the label's words, with its test, red first. It replaces the 28 % and 72 % bands; `canDrop()` stays the judge.
  - Tests: the 7 px reach; the middle 14 px; a level chosen only among legal parents; the reason of a refused row.
  - Pictures: the three targets and the refused row beside `overview-2-drag-move.jpg`.
  - Features: D37, D38, D39.

- DP15 (M) after DP14: long drags.
  - Tests: the tree as it was after Esc and after a drop; a branch opened after 600 ms kept open; the scroll speed by distance.
  - Pictures: a drag from the page's foot to its head on the 500-row page, four frames.
  - Features: D40.

- DP16 (M) after DP15: moving without dragging.
  - New: the "Move…" window as a part; the row buttons; the keys on a focused row.
  - Tests: each key's move through `canDrop()`; one undo step; the moved element selected and in view; a refused move saying why.
  - Pictures: the window, the row buttons; the window on a 390 px phone beside `phone-pages-move.jpg` (`designer-panels-measures-6.md`).
  - Features: D41, D42, D43, D44. Keeps K9.

## Which steps wait for each other?
- DP12 and DP14 both wait for DP11 and are not open together.
