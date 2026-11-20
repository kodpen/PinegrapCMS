---
name: designer-panels-steps-2
description: Plan designer-panels, steps DP6 to DP10 of the Options chain: layout by context, the spacing box and size chooser, corners, the search that also adds, the review view, copy and paste style
---

## Which steps are there?
- DP6 (S) after DP5: layout by context. Done in #10504.
  - New: a pure function answering which Layout rows an element gets from its own display and its parent's, with its test (a flex container, a child of one, both, neither).
  - Pictures: the three cases beside `options-2-style-advanced.jpg`; "Make it a container" pressed.
  - Features: D19, D20.

- DP7 (M) after DP5: the spacing box and the size chooser. Done in #10564.
  - New: a part drawn when Style › Spacing is first shown; a pure mapping between a side, a size and its class, both ways, with its test.
  - Tests, red first: every side at every size written as today's class; Shift and Alt; "auto"; a value read back from a tree saved by today's rows.
  - Pictures: the box in its three states and the chooser beside `measures-3-controls.jpg`; the same element's classes before and after.
  - Features: D16, D17. Keeps K1.

- DP7b (S) after DP7: corners on the same drawing. Done in #10633.
  - Features: D18.

- DP8 (M) after DP5: the search that also adds. Done in #10658.
  - New: a part; the index of rows (label, section, tab, CSS property, class prefix) and a pure fold-and-match function with its test. It builds on the no-match line of PR #10055 and keeps its wording.
  - Tests: Turkish letters folded both ways; "margin" finding the outer spacing rows; Enter's target (tab, section, control) for a row in a closed section of another tab; no result offering the class.
  - Pictures: the four cases of `options-3-search-review.jpg`.
  - Features: D21, D22, D23, D24.

- DP9 (S) after DP8: the review view. Done in #10699.
  - Tests: the list equal to the dots of all three tabs; "Reset all" as one undo step.
  - Pictures: the list, its foot, the question of "Reset all".
  - Features: D25, D26.

- DP10 (M) after DP9: copy and paste style, the quick strip.
  - New: a pure function that takes an element's changed Style rows and one that fits them to a target kind, with its test; the fixed list of the quick strip per element kind.
  - Tests: content, link and binding never copied; a row the target lacks left out and counted; paste as one undo step; a multiple selection.
  - Pictures: the brush on the identity row and on the canvas toolbar, the toast, the quick strip on three kinds.
  - Features: D15, D27, D28.

## Which steps wait for each other?
- DP6, DP7 and DP8 wait only for DP5 and never share a function; they are still not open together while each edits the core file.
