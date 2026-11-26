---
name: image-editor-elements
description: The Artifact Canvas's element sets in the Sticker tool (AC6): the Elements section and its seven chips, the forty shapes file, the svg path layer a press puts, its colour and width, the elements part
module: image_editor
---

## What does a person see?
- At the top of the Sticker tool's panel the section "Elements": chips Lines, Badges, Ribbons, Blobs, Arrows, Speech, Ornaments (Lines first, the choice kept while the editor is open), then the chosen set's shapes, four a row. The stickers, Picture from Files, Icons and Emoji follow.
- A press puts the shape in the middle of the stage (`stickerCentre()`), its own proportions fitted into 35 % of the visible board (`svgFit()`), dark `#212529`, named by its label, selected; one undo step.
- With element layers selected, the Sticker panel and Select's panel show Color and, when every one is stroked, Width (1 to 12 in the shape's own box units). Select's panel shows these instead of an svg layer's Fill and Outline.

## What is a placed element?
- An ordinary `svg` layer of kind `path`: `{ type: 'svg', kind: 'path', element: <id>, name, markup, vb: { x: 0, y: 0, w, h }, x, y, w, h, rot, opacity }`. It saves, animates and exports as any svg layer (`image-editor-import.md`); no new layer type.
- The markup is one `<path>` written by `M.elementMarkup()`: a filled shape `fill=<colour> stroke="none"`, a stroked one `fill="none" stroke=<colour> stroke-width stroke-linecap/linejoin="round"`. `M.elementPaint()` reads colour and width back, `M.elementRepaint()` changes them in place; markup they did not write is not an element's, and the svg panel shows instead.
- A stretched box scales the stroke with it, as an imported SVG does.

## Where are the shapes kept?
- `assets/canvas/elements.json`: `{ elements: [{ id, set, label, w, h, d, stroke }] }`, forty shapes (six lines, six badges, five ribbons, six blobs, six arrows, five speech shapes, six ornaments). `stroke` 0 is filled, else the starting width. `label` is a key of `pg_image_editor_strings_elements()`.
- Read once a page when the Sticker panel first opens (config `elements`, `pg_image_editor_elements_url()` with the file's time). `M.elementSets(data)` keeps an element only with an id `[a-z0-9-]` once, a set of `M.ELEMENT_SETS`, a label `element…`, a box 1 to 1000, a path of `M L H V C Q Z` and numbers starting with M, a stroke 0 or 1 to 12; 60 at most. An unreadable file shows "The elements could not be loaded."
- Paths are absolute and every point stays inside the box, a stroked shape 6 inside so the widest stroke is not cut on a save's picture (tested by sampling each curve).
- Licence: the shapes are the product's own, drawn for it.

## Where is the code?
- `assets/js/pg_image_editor_elements.js` (part `elements`, bundled after `glyph`): `elementsSection()`, `elementTiles()`, `addElement(e)`, `paintElements(ids, color, width, live)`, `elementPanel()`. It wraps `Editor.panels.sticker` and `Editor.prototype.svgPanel`; no core edit.
- Model section `ELEMENTS`; rules under "Element sets" in `pg_image_editor.src.css`; the chips reuse the emoji categories' scrolling row (`pgie-seg pgie-gf-cats`).
- Tests: `tests/image_editor_elements_test.php` (the check, markup and paint, the placed layer, the shipped file inside its boxes and labelled, the part, a press on an editor).
- AC10's photo frames add a chip "Frames" here (`plans/artifact-canvas-content-screens.md`).
