---
name: image-editor-import
description: Import in the image editor's top bar (E31): from this computer or Files, a picture as a picture layer, an SVG as one svg layer with unique ids, Break apart, svg layers painted on a save's canvas
module: image_editor
---

## Where is Import?
- A button before Save in the top bar (`topItems` of `pg_image_editor_artboard.js`) wherever the tools include Select (not on the mini bar or Optimise). Its menu: From this computer (a file picker, pictures and `.svg`) and, with the editor's API and not a callback save (`importApi()`), From the site: the Files list of the Sticker part in a box over the editor (`openImportFiles()`, `this.confirmBox`, so Escape closes it and gives the focus back to the button), searchable, SVG files included (`sticker_files` with `svg: true`).
- A drop on the stage goes through the same `importFile()`; a paste stays as it was (pictures only).
- The Image item left the canvas rail (owner, 2026-11-04); a caller's tool list may still name `image` and gets its panel.

## What does an import make?
- A picture: a picture layer as the Image tool made it (`addPicture()`, `addFilePicture()`).
- An SVG: its text goes to the action `svg_import` (`pg_svg_sanitize()`; a file by id with `pg_image_editor_svg_file()`'s gates), then `svgImport()` makes one `svg` layer of kind `g` with `imported: true` and `fileName`: an outer `<g id>` carrying the root's id (the scope of its sanitized style rules), an inner `<g transform>` with the view box's matrix and the root's inherited paint. Its box is `M.svgFit()`: 60 % of the visible stage, centred, grown for a small drawing.
- Every id in the file gets the prefix `i<n>-` with the smallest n that clashes with no id of the document (`M.svgIdMap()`); references follow (`M.svgRenameValue()` for href and url(#…), `M.svgRenameCss()` for selectors and url(#…), a colour like `#fff` left alone). Without a root id the file gets `svg`, renamed the same way.

## What does Break apart do?
- In the panel of one unlocked imported layer: the inner group's children as a stand-alone SVG through `svgLayers()`, a layer per element, each placed by the import's matrix times its own (`M.svgPlace()`); one that would skew keeps its own matrix in a `g` as large as the import. The definitions join `doc.svgDefs`; their scope is renamed to `doc.svgRootId`, or the import's scope becomes it. One undo step.

## How does an svg layer reach a PNG?
- The `svg` type has `paint()` and `ready()`: the layer's markup with the document's definitions and root id as an SVG picture of its box (a data URL, loaded by `picture()`), drawn through `M.svgMatrix()`. An opened SVG file still saves as SVG (`svgText()`).
- Tests: `tests/image_editor_import_test.php`.
