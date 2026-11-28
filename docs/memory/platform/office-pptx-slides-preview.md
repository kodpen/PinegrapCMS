---
name: office-pptx-slides-preview
description: The .pptx preview's Slides tab (DF2): slides drawn in the browser by the image editor's painter, pictures per slide through the preview's own door as data: addresses, the left-out line, keys, limits
module: office_pptx_slides
---

## What does a .pptx show now?
- Quick Look, the file window and a channel's Preview open a presentation on two tabs (`pg-seg`): "Slides" first, "Text" the list of `office-pptx-preview.md` unchanged, drawn on first use.
- Slides: a strip of numbered thumbs 148 px wide in the slide's ratio (chosen one with the selected edge, a hidden one dimmed and marked "Hidden slide"), the chosen slide fitted beside it on the look's ground and never larger than its own size, "3 / 24" with Previous slide and Next slide, "Speaker notes" folded under it (from the text reader). Below 768 px the strip is one row under the slide.
- Arrow keys, Page Up/Down, Home and End change the slide while the focus is in the strip or on the slide. A screen reader hears "Slide 3 of 24: <title>".
- A slide not drawn yet is a skeleton in its ratio; one that fails shows "This slide could not be drawn." and the Text tab still reads it.
- The piece is in Design examples as "Slides" (`pg_look_examples_slides_pattern()`).

## How is it drawn?
- DF1's choice (`office-pptx-slides-measure.md`): the browser's painter, nothing drawn kept on the server.
- `pg_office_read()` adds `design` to a pptx answer: `pg_office_pptx_slides_drawn()` in `includes/fn/office_pptx_slides.php` (loaded where used) gives width, height, count, truncated, pages `{n, hidden, doc}`, report and `left`. Null when the design reader refuses the file: then the text list alone.
- `renderSlides()` in `pg_office_view.js` loads the editor (`pgImageEditor.load()`) and paints each slide as `workspace_canvas_picture.js` does (`M.canvasOpenDoc()`, a painter on `Editor.prototype`, `layersReady()`), then `paintLayers()` into its own canvas at the thumb's or the stage's size (up to 2x pixel ratio). One slide at a time, the chosen first; thumbs when they come into view; the stage again on resize.
- Without the editor on the page, the documents or the caller's `slidePictures` option, the text list alone, as before.

## How do the pictures reach the painter?
- Not through `design_file` (it needs an edit right and makes files: `ui/image-editor-design-file.md`). The same preview door, with `slide` (1-based): `explorer_office_preview` (file manager, its view checks) and `ws_file_office` (channel readers) answer `{pictures: {layer id: data: address}, lost}` from `pg_office_pptx_slide_pictures()`.
- Each picture is cut and flipped by `pg_image_editor_design_picture_bytes()`. A part with the same cut is read once a slide; at most 24 MB of pictures an answer (`PG_OFFICE_PPTX_SLIDE_PICTURES`), the rest counted in `lost` and not drawn.
- The client keeps only `data:image/(png|jpeg|gif|webp);base64,` addresses (`slides.doc()`); any other picture layer is left out, so a picture never comes from elsewhere. Asked once per slide per open window.

## What does the left-out line say?
- `pg_office_pptx_left_line()` from the mapper's summed report: "Shown approximately. Not drawn: Charts (2), Tables (1). 3 font(s) replaced. 1 element(s) simplified." Kinds outside the canvas's labels (such as `shape`) are summed as "Other elements". No line when nothing is counted.
- Not counted (measure's leftovers, a later step's): master and layout text styles, a shape style's text colour, a theme font replaced without being named.

## What stays?
- The preview's limits and refusals (`office-pptx-preview.md`, `office-files.md`); past the slide limit "Only the beginning is shown." under the slides too. View only; Present and Edit in Artifact Canvas: `office-pptx-present.md`.

## How is it tested?
- `tests/office_pptx_slides_test.php`: the line, a written presentation read with `design`, one slide's pictures, refusals, and the view's helpers (`pgOfficeView.slides`: doc, fit, key, name) under Node. The drawing itself was not run in a browser here.
