---
name: image-editor-text-styles
description: The Artifact Canvas's text styles and font pairs at the top of the Text panel (AC5): the three Add buttons, the twelve pairs file, their sizes, the group a pair makes, the textstyles part
module: image_editor
---

## What does a person see?
- At the top of the Text tool's panel three full-width buttons, each in its own style: "Add a heading", "Add a subheading", "Add body text". A press puts a text with those words in the board's middle, selected.
- Under them the section "Font pairs": twelve tiles, two a row, each a heading and a line in the pair's two Google faces. A press puts both texts, stacked in the middle, as one group (`image-editor-groups.md`), selected; one undo step.
- Not on a caller's mini bar (`tools.mini`, the screenshot marker). Select's panel for a text does not show them.

## What sizes and looks do they take?
- Sizes from the board's shorter side (`viewRect()`), never under 12 px: heading 0.1, weight 700, line height 1.1; subheading 0.06, 600, 1.2; body 0.035, 400, 1.4 (`M.TEXT_PRESETS`). A pair: heading 0.09 (line height 1.1), line 0.04 (1.3), each with the weight the file names; the gap between them is 0.6 of the line's size.
- Centred and plain (no background, outline, shadow, spacing); the font (a button) and the colour come from the style last chosen (`editor.textStyle`), so a blank board's texts start dark.
- They are ordinary text layers with `g:<family>` fonts: they save, translate, animate and export as typed ones.

## Where are the pairs kept?
- `assets/canvas/font-pairs.json`: `{ pairs: [{ id, head: { family, weight, text }, line: {…} }] }`. `text` is a label key (`fontPair<Name>Head` / `Line`) of `pg_image_editor_strings_textstyles()`, so a pair's words are `lang()` words placed in the panel's language.
- Read once a page when the Text panel first opens (config `fontPairs`, `pg_image_editor_font_pairs_url()` with the file's time); nothing joins the first load. `M.fontPairs(data, known)` keeps a pair only when both families are in the Google Fonts catalogue (`image-editor-fonts.md`), the id is `[a-z0-9-]`, the key a `fontPair…` key; a weight not in `TEXT_WEIGHTS` becomes 400; twelve at most. An unreadable file shows "The font pairs could not be loaded."
- A tile links its two faces as it is drawn (`loadTextFont()`); the stage refits the texts when a face comes later.
- Licence: the words and pairings are the product's own; the faces are Google Fonts under the SIL Open Font License, as the Text tool's.

## Where is the code?
- `assets/js/pg_image_editor_textstyles.js` (part `textstyles`, bundled after `fonts`): `textStylesPanel()`, `fontPairTiles()`, `addStyledTexts(styles, words)`. It wraps `Editor.panels.text` to put its rows first; no core edit.
- Model section `TEXTSTYLES`: `textPreset()`, `fontPairs()`, `fontPairStyles()`, `textStack()` (boxes stacked in the board's middle, each centred across). Rules under "Text styles and font pairs" in `pg_image_editor.src.css` (sizes on the look scale: 24, 17, 14 px).
- Tests: `tests/image_editor_textstyles_test.php` (the model, the shipped file against the catalogue and the labels, a press on an editor).
