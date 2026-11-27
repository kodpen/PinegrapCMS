---
name: image-editor-design-chooser
description: The design file's window "Open a design file" (DF4): the New image dialog's door, page tiles, many canvases or scenes of one canvas, the report line under the top bar
module: image_editor
---

## Where are the doors?
- Import's menu "A design file (.pptx)" (`image-editor-design-file.md`): one page picked joins the open canvas.
- The New image dialog's "Open a design file", under its sizes, only for a canvas made in a folder of Files (save mode new with `save.folderId`, the File Manager's New image) and with `importApi()`. Under the button how the file is made (Canva: Share, Download, PPTX; a Website design has no download); the reading and a refusal are written there. The dialog closes (as Cancel) once the file is read.
- `Editor.newImageRows` (artboard part): functions on the editor giving a row of the New image dialog under its sizes, or null; they get `close()`. Rows above the sizes are `Editor.newImageHeads` (AC7, `image-editor-starts.md`).

## What does the window show?
- `designChoose()` in `pg_image_editor_design.js`: the pages as tiles in the file's order, each drawn small with `layerNode()` (`designThumb()`; a picture still `pptx:` is a grey box, `designThumbDoc()`), its number and a tick. A page with something left out or simplified (`designPartial()`) carries the amber dot and the title "Not everything on this page could be brought in.".
- From Import the tiles are radios, the first picked, button "Add the page". From the New image dialog every page is ticked, the foot says "Open {n} pages as {n} canvases" (or "...as scenes of one canvas"), the tick box "As scenes of one canvas", Open. Open waits with a sentence when nothing is ticked or scenes exceed `M.SCENE_MAX` (50) (`designRoom()`). `truncated` adds "Only the first {n} pages of this file can be opened.".
- A file of one page skips the window. Keys stay in it (Escape cancels, Tab goes round). A refusal while opening stays in the window as a sentence.
- Tiles scroll and the foot stays in view; under 600 px two tiles a row.

## What does Open make?
- `designOpenPages()`: each ticked page a canvas file in the dialog's folder named "<file name> <page number>" (`designName()`), or with the box ticked one canvas "<file name>" with a scene a page, the first open (`designScenes()`, the model's `sceneAdd()`/`sceneOpen()`). Each is drawn and saved as any new canvas (`sendBody('new', { folder })`, action save), one after the other; a failure stops the rest and says "{done} of {n} canvases were made.".
- The caller's `options.onCreated(files)` hears of them (the File Manager reloads the folder). The first then opens in the editor (`designOpenSaved()`): save mode replace on that file, nothing to undo or save.

## What is the report line?
- `designReportLine()`: a line under the top bar after Open (and after Import's page): "{texts} texts and {pictures} pictures brought in.", "Fonts replaced: ..." with the families, "{n} elements left out."; Details opens the list (font replaced by stand-in, each kind left out with its count, text boxes simplified); × closes it. Kind labels are `designLeft_<kind>` (chart, table, diagram, object, media, ink, gradient, pattern, picture, mask, other).

## How is it tested?
- `tests/image_editor_design_test.php` (`test_image_editor_design_chooser`): ticks, names, the amber mark, the tile's document, scenes, the report's numbers with PHP's empty arrays, the scene cap, under Node. The window, saving and the report line were run once in a headless browser with a stubbed API; not against a database.
