---
name: image-editor-design-file
description: Open a design file (.pptx from Canva) in the Artifact Canvas as editable layers: why the .pptx, the rules, action design_file, pictures into Files, refusals, Import's "A design file (.pptx)"
module: image_editor_design
---

## What is it, and why the .pptx?
- Owner (2026-11-26, poll 230, `workflow/decisions-made-product-113.md`): a design downloaded from Canva (Share, Download, PPTX) opens in the canvas, each page a canvas or a scene. Built in PRs #11840, #11872, #11889, #11913.
- Architect (2026-11-26): the .pptx, not the .pdf: it keeps each text a box with paragraphs and style, each picture apart with its crop; a PDF gives drawn glyphs. Read on the owner's ten-page file (never committed).
- Owner (2026-11-26): a Canva design of the Website kind is published, not downloaded: the doors' hint says it gives no file.
- Open: the Page Design Studio's Canva start reading the same file for a page's texts and pictures, asked of the owner.

## Which rules bind it?
- Architect (2026-11-26): no Canva call and no library; own reader (`platform/office-pptx-design-reader.md`) and mapper (`platform/office-pptx-canvas-mapper.md`), the package opened through the preview's gates (`platform/office-files.md`).
- A text box is one text layer, a paragraph a new line; differing runs take the first run's style, counted as simplified. Groups resolve to places; turns and flips are kept; charts, tables, effects are left out and counted.
- Fonts are looked up by family and replaced when the catalogue lacks one; an embedded font is never taken from the package (its licence is the design tool's). Picture parts become the site's files only as parts of the opened canvas (`api/canva-api-terms.md`).
- Nothing joins the canvas's first load: `assets/js/pg_image_editor_design.js` (bundle `pg_image_editor_design.min.js`) loads from config `design` by `designScript()` when a door is used. Labels `design*` in `pg_image_editor_strings_artboard()`.

## What does the action design_file do?
- `includes/fn/image_editor_design.php`. `{ name, pages?, design? | upload?, file_id | folder_id }` + the bytes after the request's line, or in parts (upload kind `design`, `image-editor-upload.md`).
- Without `pages` it reads: each page's document and report (pictures still `pptx:` parts), `count`, `width`, `height`, `truncated`, the summed report, and `design`, the id the file is kept under in `data/temp/image_editor_uploads` for a day.
- With `pages` (1-based, at most 100) the folder is checked first (edit right, not the Recycle Bin; none: "A design file is opened into Files: save this image in the File Manager first."); pictures become files and the kept package is removed.

## How does a picture become a file?
- `pg_image_editor_design_picture_bytes()`: up to 32 MB; PNG, JPEG, GIF, WebP by bytes. A crop, flip, GIF or WebP is redrawn with GD (50 MP at most): JPEG stays JPEG, the rest PNG. SVG, EMF and others are left out (`left.picture`).
- Saved as a picture from this computer (`ui/image-editor-canvas-save.md`): upload cap, name "<file name> <page>.<ext>", own files row; one part with the same cut once per open; 300 pictures an open.

## What is refused?
- No ZIP: "This is not a .pptx file. In Canva: Share, Download, then PPTX."; the reader's sentences: too large (part 64 MB, package 256 MB), a part not readable; one request too large without parts: 413.

## What do the doors do?
- The New image dialog's "Open a design file" and the window "Open a design file": `image-editor-design-chooser.md`. A .pptx of Files: Edit in Artifact Canvas (`platform/office-pptx-present.md`).
- Import's "A design file (.pptx)": one page joins as one group (`designPlace()`): board colour a rectangle under it, new ids, scaled into the board and centred (`M.groupScale()`, `M.textResize()`), one undo step, a toast "Page {n} added: {texts} texts, {pictures} pictures.".

## How is it tested?
- `tests/image_editor_design_test.php`: pure parts, GD's cut and flips, a two-page presentation the test writes, `designPlace()` under Node. Not in a browser or against a database.
