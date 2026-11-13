---
name: image-editor-glyph
description: The Design Canvas's Bootstrap Icons and emoji in the Sticker tool: the glyph layer, where the icon font and map come from, the emoji set and its recent row
module: image_editor
---

## What does the Sticker tool add?
- Under the stickers and "Picture from Files", two sections that open on demand (`pg_image_editor_glyph.js`, which wraps `Editor.panels.sticker`, so `pg_image_editor_sticker.js` is unchanged):
  - "Icons": a search box over the Bootstrap Icons names (English: the box and an empty result say so) and the first 120 matches (`M.iconSearch()`: names starting with the query first, a space read as a dash).
  - "Emoji": a recent row (the last eight, `M.emojiRecent()`, browser storage `pgie-recent-emoji`) and `M.EMOJI` by category (eight categories of 32 to 40).
- A press adds a square glyph a fifth of the picture's shorter side in the middle of the stage, selected. With glyphs selected, icons get a colour (an emoji keeps its own colours); duplicate, forward, backward and delete are the Sticker panel's own row.

## What is a glyph layer?
- `{ type: 'glyph', set: 'bi'|'emoji', code, color, x, y, w, h, rot, opacity }`: an icon's `code` is its code point, an emoji's the emoji itself. `M.glyphText(l)` gives the characters, `M.glyphSize(l)` the font size (the box's shorter side, so a stretched box keeps the glyph's shape).
- The stage (`node`, SVG text, `dominant-baseline: central`) and the save (`paint`, `fillText`, `textBaseline: middle`) draw the same character in the middle of the box. Upright under a turn or flip (`UPRIGHT` in the model).
- The server does not read layer types: a glyph reaches it only drawn into the overlay.

## How is a placed one changed?
- With one unlocked sticker or glyph selected, its panel shows Change (`swapButton()`): the Sticker tool opens (a glyph's icon or emoji section open) with a note and Cancel; a pick in the grid, icons or emoji replaces that layer in one undo step (`swapGlyph()`, the hook in `addSticker()`/`addGlyph()`) instead of adding one (#6351). Another selection or tool ends it.
- `M.swapGlyph(l, pick, oldText)` keeps turn, opacity, motion and every other field, and what the person changed: a label other than the old kind's text (`stickerKindText()`), a colour or ink other than its kind's in `M.STICKERS` (an icon's other than `#212529`); else the pick brings its own label, fill and ink, so the grid's pick is what appears (review of #6351). The pick keeps its own proportions (a sticker's `w`:`h`, 1:1 for a glyph), fitted inside the old box about its centre; same proportions keep the box. The old type's own fields go; the colour stays on an emoji.

## Where do the icon font and map come from?
- The panel's own `assets/fonts/bootstrap-icons/` (font and `bootstrap-icons.json`, name => code point); `pg_image_editor_icon_urls()` puts both addresses, with the file's time, in the config as `icons`. No copy of the map in the bundle.
- The font is loaded once per page with `FontFace` under the name `pgie-bi`, so the editor also draws icons on a site page without the panel's icon stylesheet; the map is fetched when "Icons" opens. A save waits for the font (`ready`); a document opened with icons draws again once it is there.
- Emoji use the system's emoji fonts: each viewer sees their own system's drawing, and the saved picture keeps what the saving browser drew.

## Tests?
- `tests/image_editor_glyph_test.php`: the emoji set and its labels, the icon search, recent emoji, `glyphText()`, `glyphSize()`, `swapGlyph()`, upright under a flip, the shipped font and map, the bundle order.
