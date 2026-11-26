---
name: image-editor-design-file
description: The Artifact Canvas's design file (DF3): action design_file reading a .pptx into pages, its pictures kept in Files, limits and refusals, Import's "A design file (.pptx)" adding one page as a group
module: image_editor_design
---

## What is it?
- Step DF3 of `plans/artifact-canvas-design-file.md`: a .pptx (Canva: Share, Download, PPTX) opened in the canvas. Server: `includes/fn/image_editor_design.php`, loaded by `functions.php`, reading with `pg_pptx_design_read()` (`platform/office-pptx-design-reader.md`) and mapping with `pg_pptx_canvas_slide()` (`platform/office-pptx-canvas-mapper.md`).
- Browser: `assets/js/pg_image_editor_design.js`, its own bundle `pg_image_editor_design.min.js`, never in the canvas's first load: `designScript()` (artboard part) loads it from config `design` the first time Import's item is used. Labels `design*` sit in `pg_image_editor_strings_artboard()`.

## What does the action design_file do?
- `{ name, pages?, design? | upload?, file_id | folder_id }` + the file's bytes after the request's line, or in parts (upload kind `design`, `image-editor-upload.md`).
- Without `pages` it only reads: every page's document and report (pictures still `pptx:` parts), `count`, `width`, `height`, `truncated`, the added-up report, and `design`: the id the file is kept under in `data/temp/image_editor_uploads` (kind `design`, a day like any upload), so the pages are opened without sending it again.
- With `pages` (1-based, at most 100) the folder is checked first (`file_id`'s folder or `folder_id`, edit right, not the Recycle Bin; none: "A design file is opened into Files: save this image in the File Manager first."). Those pages come back with their pictures kept as files and the kept package is removed.

## How does a picture become a file?
- `pg_image_editor_design_picture_bytes()`: the part read with `pg_zip_entry_contents()` up to 32 MB; PNG, JPEG, GIF or WebP by its bytes that `getimagesizefromstring()` reads. A cut, a flip or a GIF/WebP is drawn again with GD (`pg_image_editor_design_redraw()`, 50 MP at most): a JPEG stays one, the rest become PNG. SVG, EMF and the rest are left out.
- Kept as a picture from this computer is on a save: the site's upload cap, a unique name "<file name> <page>.<ext>", its own files row (`pg_image_editor_record()`); one part with the same cut and flips once per open; 300 pictures an open.
- `pg_image_editor_design_point()` points each layer at its file (`src`, `fileId`); a layer whose picture was left out is removed and counted in the report as `left.picture`.

## What is refused, and how?
- A file whose bytes are no ZIP: "This is not a .pptx file. In Canva: Share, Download, then PPTX." (also on the first part of an upload). The reader's sentences otherwise: too large (part 64 MB, package 256 MB), a part not readable. Larger than one request without parts: the upload's 413.

## What does Import do with it?
- Import's menu "A design file (.pptx)" (with `importApi()`): a file picker, the read, `designChoosePage()` (the first page; DF4's chooser takes its place), then the page asked for.
- `designPlace()`: the page's board colour (not white) a rectangle under its layers, a locked background unlocked, new ids, one new group (`M.groupNewId()`), scaled as one into the board (`viewRect()`) contained and centred with `M.groupScale()`, letters by `M.textResize()`, outlines and corner radii by the same factor. One undo step; the group is picked; a toast "Page {n} added: {texts} texts, {pictures} pictures.".

## How is it tested?
- `tests/image_editor_design_test.php`: the pure parts, GD's cut and flips, a two-page presentation the test writes read and its picture taken out cut, and `designPlace()` under Node. Not run in a browser or against a database: the files rows and the menu item.
