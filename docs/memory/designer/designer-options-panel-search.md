---
name: designer-options-panel-search
description: The Search... box at the top of the designer's Options panel (#sd-props-search, filterPropertiesPanel()): what it matches, how Turkish İ/ı and capitals are folded, the test
---

## What does the Options panel's Search... box match?
- `filterPropertiesPanel()` in `assets/js/style_designer.src.js` hides the rows (`.sd-prop-row`, `.sd-sp-row`) of every Options and Appearance section whose label does not hold the typed text; a section whose title holds it keeps all its rows. A collapsed section with a match is opened for the search and closed again when the box is emptied.
- Callers pass the box's text as typed (the input handler, and the panel redraws that re-apply a search); the function folds it itself.

## How are Turkish letters and capitals compared?
- Both the typed text and each title and label go through `_sdCondFold()` (the value-condition fold, twin of `pg_sw_cond_fold()`): `toLowerCase()`, the U+0307 dot a capital İ leaves dropped, the dotless ı read as i.
- So `ipucu` finds "İpucu", `iç boşluk` finds "İç Boşluk", `ETİKET` finds "Etiket", `ıslak` finds "Islak". English behaves as before (case-insensitive).
- Before #10022, plain `toLowerCase()` turned İ into i + U+0307, so a typed i never matched, and the function never lowered the term itself (issue #10011).

## Where is it tested?
- `tests/designer_options_search_fold_test.php` cuts `_sdCondFold()` and `filterPropertiesPanel()` out of the source and runs them under Node with a small stand-in DOM; it passes quietly without Node.
