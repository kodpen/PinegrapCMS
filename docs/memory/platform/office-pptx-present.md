---
name: office-pptx-present
description: The .pptx preview's Present (slides full screen, keys, Esc) and Edit in Artifact Canvas (the File Manager opens the file in the canvas through design_file), DF3
module: office
---

## What are the preview's two buttons?
- Since DF3 (plan `document-family`): beside the tabs Slides and Text, in the head row `.office-slides-head` of `renderSlides()` (`pg_office_view.js`), "Present" and, where the window offers it, "Edit in Artifact Canvas". Both are in Design examples' "Slides" piece.
- Only the Slides view has them: a file the design reader refuses, or a window without the painter, shows the text list alone and no button.

## What does Present do?
- A near-black layer `.office-slides-present` (`--bs-gray-900`) inside the preview (so Quick Look's focus trap keeps it), asked for the browser's full screen; without the Fullscreen API it fills the window.
- It starts at the chosen slide and skips hidden slides, as a slide show does, unless every slide is hidden (`presentOrder()`). Each slide fills the screen in its ratio, larger than its own size when the screen is (`presentFit()`), drawn by the same painter as the strip.
- Keys (`presentKey()`): Right, Down, Page Down, Space, Enter next; Left, Up, Page Up, Backspace previous; Home, End; Escape closes. A click on the slide goes on. No wrap. Its keys are caught on the document before the window's; Quick Look's Escape steps back while `.office-slides-present` is on the page.
- Leaving the browser's full screen closes it; the preview then stays on the slide Present ended on. A screen reader hears "Slide 3 of 24: <title>"; the close button is "Close the presentation".

## What does Edit in Artifact Canvas do?
- File Manager only (file window and Quick Look): `explorer_office_preview` answers `canvas {folder_id, name, url}` for a pptx when the person may add a file to its folder (`pg_explorer_office_copy_allowed()`, the .xls copy's rule) and it is no ERP document.
- `openDesignCanvas()` in `view_folders.php` opens the canvas as New image does (save mode new in that folder) with `designFile {url, name}`. The editor then skips the New image dialog: `designFileOpen()` (`pg_image_editor_artboard.js`) fetches the file's bytes and hands them to `designImport(f, 'new')`, so the pages window, the `design_file` action, the canvases or scenes made beside the file and the report line are those of `ui/image-editor-design-file.md` and `image-editor-design-chooser.md`.
- A refusal or a failed fetch is the canvas's own "design file" error line; Cancel in the pages window leaves the blank canvas, closed as any.
- Not in a channel's Preview: a channel's file has no folder of Files for the canvases (Present is there).

## How is it tested?
- `tests/office_pptx_slides_test.php` (`test_office_pptx_slides_present_helpers`): the fit, the order with hidden slides, the keys, under Node. The layer, full screen and the canvas door were not run in a browser here.

Related: docs/memory/platform/office-pptx-slides-preview.md, docs/memory/ui/image-editor-design-file.md
