---
name: image-editor-starts
description: The Artifact Canvas's "Start from a design" (AC7): the New image dialog's row, purpose chips, tiles, the designs files, the "Your picture" box filled from Files
module: image_editor
---

## What does a person see?
- The New image dialog's first row is the button "Start from a design" (not on a caller's mini bar). Opened, it shows the purpose chips All, Post, Story, Banner, Announcement, Sale, Event, Quote, Invitation (one row that scrolls) and tiles, three a row, two under 600 px: "Blank" first, then each design drawn small by the canvas's own layer drawing, its name and size under it.
- Picking a design hides the sizes, the custom size and the backgrounds; Create opens the design as the new canvas. Blank, or closing the row, brings back the blank board. A chip with no design yet says "No design for this purpose yet."; an unreadable index says "The designs could not be loaded.".
- A design's texts are ordinary text layers in the panel's language (`lang()` words, `pg_image_editor_strings_starts()`); a translation wider than its room is made smaller (`M.startTextFit()`, never under 8 px), then placed at its anchor (`M.startTextPlace()`: x by the align, y the top).
- A design opened counts as a change (the opening document is its empty board), so a save over an address keeps it.

## What is the "Your picture" box?
- An svg layer `{ type: 'svg', kind: 'g', slot: true, name: 'Your picture' }` whose markup is a light dashed rectangle (its corner radius from the design) with the words in its middle (`M.startSlotLayer()`). No new layer type; left unfilled it saves as drawn.
- Select's panel for one box: "Choose a picture" (where Files can be reached) opens the Image tool (else Sticker) with Picture from Files open. A picture from Files, or one from this computer, then fills the box instead of adding a layer.
- Filling: the picture cut to cover the box, centred (`M.startCover()`, the longer side at most 2000 px), kept as a new PNG or JPEG data picture (a save makes it a files row, as a pasted picture), a picture layer with the box's id, place, size, turn, opacity and group, one undo step (`slotPut()`). Leaving Image and Sticker ends the picking.

## Where are the designs kept?
- `assets/canvas/designs.json`: `{ designs: [{ id, purpose, label }] }`, the tiles' order; each design `assets/canvas/designs/<id>.json`: `{ id, width, height, background, layers }`, bottom layer first. Read once a page when the row first opens (config `starts`: `pg_image_editor_starts_urls()` with the index's time).
- `M.startIndex()` keeps an id `[a-z0-9-]` once, a purpose of `M.START_PURPOSES` but all, a label `start…`; 60 at most. `M.startDesign()` refuses a whole design for a wrong id, a size outside 16-8000, more than 40 layers or one bad layer.
- Layers a design may hold: `text` (label key `start…`, font sans, serif or a catalogue `g:` family, x, y, maxW), Draw's shapes `rect`, `ellipse`, `triangle`, `polygon`, `star`, and `slot` (box, radius); fields as `startLayer()` in the model.
- The first six (AC7) cover the five sizes; AC8 adds twenty-four.
- Licence: designs and words are own; faces Google Fonts (SIL OFL); no photo shipped.

## Where is the code?
- `assets/js/pg_image_editor_starts.js` (part `starts`, bundled after `elements`): `startDocument()`, `startThumb()`, the row, `slotPick()`, `slotPut()`, `slotFill()`; it wraps `addFilePicture`, `addPicture`, `svgPanel` and the Image panel.
- Core hook (artboard part): `Editor.newImageHeads`, rows above the sizes, each `fn(api)` with `api.close()` and `api.choose(make)`; `make()` gives `{ artboard, layers }` and `closeNewImage()` opens it. Rows under the sizes stay `Editor.newImageRows` (`image-editor-design-chooser.md`).
- Model section `STARTS`; rules under "Start from a design" in `pg_image_editor.src.css`.
- Tests: `tests/image_editor_starts_test.php` (model, the shipped six, a design opened and a box filled on an editor). The dialog and a fill from Files were not run in a browser.
