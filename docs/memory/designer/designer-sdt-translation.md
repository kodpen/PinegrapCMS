---
name: designer-sdt-translation
description: Designer _sdT() rules: editor data block must print before the designer script; English keys, no embedded numbers or variables, price keys, palette labels.
---

## Why must the editor data come before the script?
- `includes/designer_screen.php` prints the `OUTPUT_PATH` / `sdRegionData` / `sdDesign` block first, then the designer script (`pg_js_bundle_tags('assets/js/style_designer.min.js', false)`, not deferred). **Order is mandatory:** `_sdT()` reads the map at call time, and module-level records (`COMPONENTS`, `CONTENT`, form palette) are built while the file parses.
- If the data block comes later, labels freeze on the English key and no tr.json edit changes them. Symptom "some places untranslated" is a late map, not a missing translation.
- Any new global the editor needs at parse time goes into this block, not into `$(document).ready`.

## What are the `_sdT()` key rules?
- **The key is English.** Turkish inside a key (`_sdT('Studio Paketi')`) makes translation impossible: Turkish and English sites see the same Turkish text.
- **No number embedded:** `_sdT('Tab {var}', 1)`, `_sdT('Item {var}', n)`; `'Tab 1'` splits the translation into three and breaks the `{var}` rule.
- **No variable embedded.** Translation is found by key and replaces the whole text; a name embedded in it is lost with the translation. Use `{var}`: `_sdT('It stays in {var} as it was.', owner)`.
- **Prices go through `_sdT()`:** key is the English source (`_sdT('$199')`), Turkish `₺199` lives in tr.json. A raw `'₺199'` prices an English site in lira.
- **Palette record `label` and visible texts in `defaultProps` are wrapped in `_sdT()`.** No check catches a raw literal there: `tools/check_lang.php` reads only `lang()` / `_sdT()` calls, and the audit tool (`core/designer-i18n-audit-tool.md`) takes a single capitalized word in that position for an identifier and stays **silent**; verify by eye.
- **Placeholder text is never Latin.** A dropped component's default text tells the operator what to do; Lorem ipsum is neither readable nor clearly meant to be deleted.

Related: docs/memory/designer/designer-layer-names.md, docs/memory/core/sdt-designer-js-text-rules.md, docs/memory/core/translation-rules.md
