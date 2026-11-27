---
name: image-editor-tabler
description: Tabler Icons beside Bootstrap Icons in the Artifact Canvas's Sticker tool (AC12): the set switch, the shipped file and its MIT licence, the svg path layer, its colour and stroke width
module: image_editor
---

## What does a person see?
- The Sticker tool's "Icons" section starts with a segmented control "Bootstrap" / "Tabler" (Bootstrap first, the choice kept while the editor is open). The search box, its English-name note and the first 120 matches are shared by both sets.
- Tabler's file is read the first time "Tabler" is picked; a failed read says "The icons could not be loaded." and the next pick tries again.
- A press puts the icon in the middle of the stage (`stickerCentre()`), a fifth of the visible board's shorter side as a Bootstrap icon, dark `#212529`, stroke width 2, named "Icon · <name>", selected; one undo step.
- With Tabler icons selected, the Sticker panel and Select's panel show "Icon colour" and "Stroke width" (0.5 to 3 in quarter steps, in the icon's 24 box) instead of an svg layer's Fill and Outline.
- While Change replaces a Bootstrap icon or an emoji (`image-editor-glyph.md`) the switch is hidden and Bootstrap shows: a Tabler icon is not a glyph and has no Change.

## What is a placed Tabler icon?
- An ordinary `svg` layer of kind `path`: `{ type: 'svg', kind: 'path', tabler: <name>, name, markup, vb: { x: 0, y: 0, w: 24, h: 24 }, x, y, w, h, rot, opacity }`. It saves, animates, exports and is drawn by the server (librsvg) as any svg layer; no new layer type, no font.
- The markup is one stroked `<path … fill="none" stroke stroke-width stroke-linecap/linejoin="round"/>` written by `M.tablerMarkup()`; `M.tablerPaint()` reads colour and width back, `M.tablerRepaint()` changes them; markup they did not write is not a Tabler icon's, and the svg panel shows instead.

## Where do the icons come from?
- `assets/canvas/tabler-icons/icons.json`: `{ version, icons: { name: path } }`, Tabler Icons 3.51.0, 5,181 outline icons, about 940 KB (220 KB compressed). Config `tabler`, `pg_image_editor_tabler_url()` with the file's time.
- Built by `php tools/build_tabler_icons.php <unpacked @tabler/icons package>`: it keeps the outline icons drawn by stroke alone (52 with a fill, an own stroke or an opacity are left out) and joins each icon's paths into one. An update: unpack the new release, run the tool, commit both files.
- `M.tablerIcons(data)` keeps a name `[a-z0-9-]` and a path of path commands and numbers starting with a move, 4,000 characters at most.
- Licence: MIT, copyright Paweł Kuna; the release's `LICENSE` ships beside the file (`assets/canvas/tabler-icons/LICENSE`). Brand logos come with the set as they do in Bootstrap Icons.

## Where is the code?
- `assets/js/pg_image_editor_tabler.js` (part `tabler`, bundled after `elements`): `iconSetSwitch()`, `tablerFill()`, `addTabler()`, `paintTabler()`, `tablerPanel()`; it wraps `Editor.panels.sticker` and `Editor.prototype.svgPanel`. The Icons section of `pg_image_editor_glyph.js` asks for the switch and the grid when the part is there (`glyphs.set`).
- Model section `TABLER`; rules under "Tabler Icons" in `pg_image_editor.src.css`; labels `pg_image_editor_strings_tabler()`.
- Tests: `tests/image_editor_tabler_test.php` (the check, the search, markup and paint, the placed layer, the shipped file and licence, the part, a press on an editor).
