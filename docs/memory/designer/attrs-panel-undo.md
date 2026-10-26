---
name: attrs-panel-undo
description: Undo steps of the designer's Attributes panel text fields (id, prop-bound attributes, custom attribute name and value) and when its style row is written
---

## When does an Attributes text field take its undo snapshot?
- Before its first change: `_sdUndoBeforeFirstInput(inp)` (style_designer.js, beside `addClassToken()`) returns a function each `input` handler calls first; it runs `saveState()` once per focus. Used by the id field, the prop-bound attributes (an image's src, alt…), and a custom attribute's name and value.
- Before (until PR #1729), these fields called `saveState()` on `change`, after the value was already written: the snapshot held the new value and Undo could not bring the old one back. A custom attribute's name took no snapshot at all.

## When is the style row written?
- On Enter or when it loses focus (`change`), once per new value, through `_writeInlineStyleValue()` (one `saveState()`, then the split into options and `render()`). The virtual style row (only option-made declarations) works the same way.
- Before, every keystroke wrote: one undo step per character, and a half-typed declaration (`font-fam`) was split into options and redrawn.

## Where is it tested?
- `tests/designer_attrs_undo_test.php` runs the panel's binding block (from `// ── id change ──` to `// ── Add a new attr ──`) under Node on fake fields; keep those two markers.
