---
name: designer-options-panel-search
description: The label filter of the Options panel's search box (filterPropertiesPanel()): what it hides, sections without rows, the No option matches line, Turkish İ and capitals
---

## What does the Options panel's search box filter?
- The box is "Search or add an option…" (DP8); its results list, the CSS property and class prefix matches and the class offer are in `designer-options-search.md`. This file is the label filter under it.
- `filterPropertiesPanel()` in `assets/js/style_designer.src.js` hides the rows (`.sd-prop-row`, `.sd-sp-row`) of every Options and Appearance section whose label does not hold the typed text; a section whose title holds it keeps all its rows. A collapsed section with a match is opened for the search and closed again when the box is emptied.
- A section built without option rows (Translation, Quick Layouts) is found by its title or by the text of its own buttons and labels, not by a select box's options; its collapsed body opens for the search (#10012).
- A shown section nested in another keeps the outer one shown.
- When nothing is shown and the part 'optsearch' has not run yet, a line under the box (`#sd-props-search-empty`, `_sdPropsSearchEmpty()`) says "No option matches “word”." with the trimmed word as typed; it sits outside `#sd-properties`, so a panel redraw does not remove it, and an empty box or any match hides it (#10012).
- Callers pass the box's text as typed (the input handler, and the panel redraws that re-apply a search); the function folds it itself.

## How are Turkish letters and capitals compared?
- Both the typed text and each title and label go through `_sdCondFold()` (the value-condition fold, twin of `pg_sw_cond_fold()`): `toLowerCase()`, the U+0307 dot a capital İ leaves dropped, the dotless ı read as i.
- So `ipucu` finds "İpucu", `iç boşluk` finds "İç Boşluk", `ETİKET` finds "Etiket", `ıslak` finds "Islak". English behaves as before (case-insensitive).
- Before #10022, plain `toLowerCase()` turned İ into i + U+0307, so a typed i never matched, and the function never lowered the term itself (issue #10011).

## Where is it tested?
- `tests/designer_options_search_fold_test.php` cuts `_sdCondFold()` and `filterPropertiesPanel()` out of the source and runs them with `_sdPropsSearchEmpty()` under Node with a small stand-in DOM; it passes quietly without Node.
