---
name: image-editor-outline
description: The Design Canvas's text to outlines: the model's own OpenType reader, where the font file comes from (action font, data/fonts), the path it makes, what is refused
module: image_editor
---

## What does Convert to outlines do?
- Under the text controls of selected texts (Select's or the Text tool's panel), `pg_image_editor_outline.js` wraps `textControls()`. The texts become path layers in one undo step: same id, place, angle, colour, opacity and other fields; the letters are drawn on the text tool's own layout (`editor.textLayout(l)`, a hook in `pg_image_editor_text.js`).
- The path (`M.outlineLayer()`): the first contour is its `knots`, the others its `subs` (`image-editor-vector.md`), closed, `fill: 'solid'`, `stroke: 0`. A text outline becomes `stroke` (twice its width) with `ink` (its colour), drawn under the fill; the shadow becomes the path's `shadow` in px. A background becomes a rounded `rect` layer under the path, which then carries the shadow.

## How are the letters read?
- The model's OUTLINE section reads the file itself (`M.fontRead()`): TrueType `glyf`/`loca` (quadratic curves made cubic, composite glyphs with their offsets and matrices) or CFF Type 2 charstrings (subroutines, flex; hints skipped), `cmap` formats 4 and 12, `hmtx`, and pair kerning: GPOS pair adjustments (formats 1 and 2, extension lookups) of the `kern` feature, else the `kern` table's format 0. WOFF, WOFF2, collections and CID-keyed CFF are refused.
- `M.outlineText()` places each line on the layout's baseline; a spaced line takes the layout's character x, otherwise the font's advances and kerning from the aligned side. No shaping: ligatures and contextual forms are not applied.

## Where does the font file come from?
- Every font that is not a system stack is a Google family (`g:<family>`, site fonts included, `image-editor-text.md`). Action `font` { family, weight, italic } (`pg_image_editor_action_font()`) asks Google's css2 API as a non-browser, which names a TrueType file (browsers get WOFF2, which needs Brotli to read), fetches it once into `data/fonts/<family>-<weight>[i].ttf` (at most 400 files) and streams it. Faces are tried nearest first: the weight and style, the weight upright, the family's own face.

## What is refused?
- A system font (no file: `outlineSystemFont`), scripts that need a shaper (Arabic, Indic and others, `M.outlineNeedsShaping()`: `outlineShaping`), letters the font lacks (`outlineMissing` names them), a file that does not load (`outlineFontError`). Locked or empty texts are left as they are.

## Tests?
- `tests/image_editor_outline_test.php`: the reader on `tests/fixtures/outline/fixture.ttf` and `.otf` (made with fontTools: contours, a hole, implied curve points, a component, kern table, GPOS glyph and class pairs), the outline against the layout, the path layer and its refit, the shaping check, the action's face, stylesheet and file helpers.
