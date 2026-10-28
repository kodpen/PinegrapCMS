---
name: options-panel-style-labels
description: Labels of the options panel selects built from utility classes (selStyleGroup) and of the colour pickers: how options and placeholders are translated, the Text/Background Color row keys (#2046)
---

## How does a class-driven select label its options?
- `selStyleGroup(nodeProps, classes, placeholder)` (style_designer.js) draws one `<select data-stylegroup>` per family of mutually exclusive utility classes (shadow, radius, opacity, border width, font weight …). Each option's label comes from `_sdStyleOptionLabel(cls)`: the class without its family prefix (`text-opacity-25` → "25", `rounded-top-3` → "top 3"), translated when every word left is a known size, shape or side (`sm`, `lg`, `none`, `circle`, `pill`, `top`, `end`, `bottom`, `start`) or a number: "Küçük", "Daire", "Üst 3". The bare `shadow` class, whose label was empty, reads `_sdT('Regular')`.
- A label with a word that has no translation keeps its old class-derived form in full (`fw-lighter` → "Lighter", `text-decoration-none` → "Decoration none"): translating one word of it would print "Decoration Hiçbiri". Add the word to the map in `_sdStyleOptionLabel()` with its `_sdT('…')` key to translate such a family.
- The prefix list strips the longer family first (`text-opacity` before `text`, `fst` before `fs`): with the old order the text opacity options read "Opacity 25" and the font style ones "T italic".

## Why is the placeholder translated inside selStyleGroup()?
- Most callers pass the bare literal `'Default'` or `'None'` as the placeholder; the function maps those two through `_sdT()` and prints any other placeholder (one the caller already translated, `_sdT('Auto')`) as given. One change covered every row instead of twenty call sites. Symptom fixed: every such select opened with "Default" on a Turkish panel while the Display/Position selects beside it said "Varsayılan" (#2046).

## Which keys name the colour rows?
- Colors section: `_sdT('Text Color')` and `_sdT('Background Color')` for the class pickers, `_sdT('Custom Background Color')` for the inline-style colour or gradient row under them. `'Text Col'` is the block templates' column name ("Metin Sütunu") and must not label the colour row; `'Bg Col'` and `'Bg Color'` both read "Arka Plan Rengi", so two rows had the same name.
- `colorSelectGroup()`'s visible button says `_sdT('None')` like its hidden select; the tooltip, popover, background position and object position selects say `_sdT('Top')` like their other options.
- Test: `tests/designer_style_option_labels_test.php` cuts `_sdT()`, `capFirst()`, `_sdStyleOptionLabel()` and `selStyleGroup()` out of the script and runs them under Node with the Turkish values of the keys, and pins the row keys.

Related: docs/memory/core/sdt-designer-js-text-rules.md, docs/memory/designer/designer-panel-popover-and-rows.md
