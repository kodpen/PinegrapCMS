---
name: image-editor-text
description: The image editor's text layers: fields, the one line layout the stage and the save share, typing in place, system and site fonts, resize scaling the letters, the panel
module: image_editor
---

## What is a text layer?
- `type: 'text'` with the layer box (`x, y, w, h, rot, opacity`) and `text`, `font` (`sans`, `serif`, `hand`, `display`, `mono` or `g:<family>`), `size` (px of the rotated space), `weight` (300-900), `italic`, `align`, `lineHeight` (times the size), `color`, `bg` (a colour or `''`). `spacing`, `pad`, `radius`, `outline.width` and the `shadow`'s `blur, dx, dy` are em (times the size), so a resize scales them with the letters.
- `w` and `h` follow the layout: the stage's `node()` fits them in place (no undo step) when a face loads later or another computer measures other widths; the aligned side stays (`textFit()`: left, the middle, or right; the top always).

## How do the stage and the save draw the same text?
- One layout in the model's text section, `textLayout(l, measure, metrics)`: lines break only at a new line; each line's x from the alignment, its baseline from the font's ascent and descent (`fontBoundingBox*`, else 0.8/0.2 of the size); with letter spacing every character gets its own x, measured over the line up to it so kerning stays. Tested under Node in `tests/image_editor_text_test.php`.
- The stage draws SVG `<text>` (a `<tspan>` per character when spaced), the outline as a stroke under the fill (`paint-order`), the shadow as `feDropShadow` outside the turn so it falls the same way at any angle. The save's `paint()` draws the same numbers on a canvas of the text's own and lays it on with the canvas shadow (scaled by the transform, as canvas shadows are in pixels), so the shadow falls from the whole text and the opacity covers background and letters as one.

## How does typing work?
- The Text tool: a press on a text or a handle moves, sizes and turns it as Select does; elsewhere a `<textarea>` opens over that point in the layer's letters at the view's zoom. The layer joins the document with the first letter and the whole typing is one undo step; ending with an empty text removes it.
- Double click (Select or Text) or Enter on one selected text types into it. Escape or Ctrl+Enter ends; Enter is a new line; a press on the stage ends it. A press on the panel keeps the typing on and gives the caret back after a button. While typing, the selection handles hide (`.pgie-typing`).
- A new text takes the style last chosen (`editor.textStyle`); its size, until one is chosen, is a twelfth of the picture's shorter side.

## Which fonts are offered?
- Five system stacks (no download) and the site's fonts: action `text_fonts` (`pg_image_editor_action_text_fonts()`) answers the enabled families of `style.style_custom_fonts` (the designer's JSON list; the old one-URL-a-line form names none), cleaned to letters, digits, spaces and `- _ .`. The editor links Google Fonts for a family once, as the site's pages do, and waits for the face (`ready()`) before a save.

## How does a resize work?
- `pg_image_editor_layers.js` asks a type's `resize(start, box, h)` for the fields a box handle changes. A text scales its size by the height from a corner or the top or bottom, by the width from a side, keeping the opposite corner or side in its turned frame (`textResize()`).

## What does the panel hold?
- Colour, font tiles, size, bold, italic, alignment, background colour (transparent is none); under Details: weight, line height, letter spacing, padding and corners, outline colour (transparent is none) and width, shadow (soft, hard, glow). The colours are the core's palette, `X.swatches()` (`image-editor-parts.md`), not one of the tool's own. Select's panel shows the same controls for selected texts. Labels: `pg_image_editor_strings_text()`.
