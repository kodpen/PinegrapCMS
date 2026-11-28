---
name: document-family
description: Plan (owner 2026-11-28, poll 256): own types in the centre, Office files the bridge: the .pptx preview drawn as slides by the canvas's renderer; the window head is `document-head.md`; steps DF1 to DF4
priority: 6
---

## What is asked?
- Owner (2026-11-28, `workflow/decisions-made-product-120.md`): documents, tables and presentations are the product's own types, Office files the way in and out; a .pptx is shown as it looks; the windows share one head.

## What is there today?
- A .pptx previews as a list of its slides' text, by design (`platform/office-pptx-preview.md`, #10585).
- A second reader and a mapper turn a slide into a canvas document: `pg_pptx_design_read()`, `pg_pptx_canvas_slide()` (`platform/office-pptx-design-reader.md`, `office-pptx-canvas-mapper.md`), used by the canvas's design file door (`ui/image-editor-design-file.md`).
- A canvas document is drawn outside the editor two ways: on the server where Imagick is there (`platform/image-server-draw.md`) and in a browser by the editor's painter (`workspace/agent-canvas-picture.md`).
- The canvas has scenes, Present and a PDF a page per scene (`ui/image-editor-present.md`).

## What did the architect settle? (not the owner's words)
- No new viewer and no library: the preview draws `pg_pptx_canvas_slide()`'s documents with a renderer there is. Which one is DF1's finding.
- The drawing is approximate and says so: what the mapper's report counts as left out or simplified is told in one line. The text list stays as a second tab, so nothing shown today is lost.
- The preview is view only; editing is the canvas's own door, for whoever may edit.
- The preview's limits and refusals stay (`platform/office-files.md`); a file the design reader refuses shows the text list alone.
- No schema. A drawn slide is kept, if at all, in the site's cache, never as a file of the File Manager.

## Which steps? (S under 400 product lines, M about 1,000; each waits for the one before it)
- DF1 (S) Measure: on dev, a Canva deck and a corporate PowerPoint of 20 slides or more: seconds to the first slide and to all of them by the server's draw and by the browser's painter (the editor's load counted), on a first and a second opening, and what each leaves out. Written as `platform/office-pptx-slides-measure.md`, naming the way DF2 takes. Over 3 seconds to the first slide both ways: the architect is asked before DF2.
- DF2 (M) The preview as `document-family-pptx-preview.md` says: the tabs Slides and Text, the strip, the slide, the left-out line, in Quick Look, the file window and a channel's Preview.
- DF3 (S) Full screen and the door: "Present" steps through the slides full screen (arrow keys, Esc); "Edit in Artifact Canvas" opens the file through the `design_file` action; `office-pptx-preview.md`, `office-files-scope.md` and `office-overview.md` corrected.
- DF4 (S) Census of heads: what the windows of a note, a table, the canvas, a Word file, an Excel file and Quick Look hold in their heads today (name, save state, undo, share, download, the ⋯ menu), in which order and by which code, as `document-family-census-heads.md`. The shared head drawn from it and its steps: `plans/document-head.md`.

## What waits until after the launch?
- A Word file opened as a Note, a Note saved as a Word file, a canvas saved as a .pptx, one "New" menu under the family's name, Pinegrap Artifacts (`platform/office-direction.md`).

## What is left out?
- More in-place editing of Word and Excel files than is built (`platform/office-docx-overview.md`, `office-sheet-formulas.md`).
- Editing a .pptx in place; the binary .ppt.
