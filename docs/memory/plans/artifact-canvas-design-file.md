---
name: artifact-canvas-design-file
description: Plan artifact-canvas-content, the design file (owner 2026-11-26, poll 230): a .pptx from Canva opened in the Artifact Canvas as editable layers; rules, the page chooser, steps DF1 to DF5
priority: 6
---

## What is built?
- A .pptx is read by own code and each page becomes a canvas document: a text box a text layer, a picture a picture layer, a line or shape a drawn layer, the slide's background the board's. Saved, its texts enter the translation store as any canvas text (`core/picture-text-translation-store.md`).
- What the file gives and why the .pptx: `plans/artifact-canvas-canva-census.md`. Owner's choice for many pages: `workflow/decisions-made-product-113.md`.

## Which rules does it keep? (architect, 2026-11-26, agent channel request 3137)
- No Canva call and no library; the reader is a new module beside `pg_office_pptx_read()` and opens the package through the same gates and limits (`platform/office-files.md`).
- One text box is one text layer; a paragraph break is a new line. A box whose runs differ takes its first run's style and is counted as simplified.
- A picture goes through the checks of a picture brought from the computer and becomes a file of the site (`ui/image-editor-canvas-save.md`); its crop is kept.
- Groups are resolved into places on the page; a turn and a flip are kept; what the canvas cannot hold (a chart, a table, an effect) is left out and counted.
- Fonts by the census's rule: looked up by family, replaced when the catalogue lacks one, never taken from the package.
- Nothing joins the canvas's first load: the chooser's script loads when a door is used.

## Where are the doors, and what is the chooser?
- The New image dialog gains "Open a design file" under its sizes, and Import's menu gains "A design file (.pptx)". Both hint how the file is made (in Canva: Share, Download, PPTX) and that a Canva design of the Website kind has no download (the census).
- After the file is chosen the window "Open a design file" shows the pages as tiles in the file's order, each drawn small by the canvas's own renderer from what was read, with its number and a tick. A page not read whole carries the amber mark.
- From the New image dialog every page is ticked. The foot says "Open {n} pages as {n} canvases", has the tick box "As scenes of one canvas" and the button "Open". Each ticked page becomes a canvas file in the folder the dialog was opened for, named by the file and the page's number, and the first opens; with the box ticked one canvas with a scene a page (`ui/image-editor-scenes.md`).
- From Import in an open canvas one page is picked, and its layers join the open canvas as one group fitted to the board, in one undo step.
- A file of one page skips the tiles. On a phone the tiles are two a row and the foot stays in view.
- After Open a line under the top bar reports: texts and pictures brought in, fonts replaced with their names, elements left out, with "Details" opening the list. Refusals are sentences in the window: not a .pptx, too large, a part not readable.

## Which steps? (M about 1,000 product lines; tests red first, on presentations the test writes)
- DF1 (M) done, PR #11840 (`platform/office-pptx-design-reader.md`): the reader, pure: slides of placed elements, counts of what is left out.
- DF2 (M) done, PR #11872 (`platform/office-pptx-canvas-mapper.md`): the mapper, pure: a slide to a canvas document, sizes and spacing by the census's sums, the font lookup, the report's numbers.
- DF3 (M) done, PR #11889 (`ui/image-editor-design-file.md`): the action reading the file, pictures into Files, refusals, Import's one page as a group.
- DF4 (M) in progress: the chooser window, the New image dialog's door, many canvases or scenes, the report line, the phone.
- DF5 (S) Close-out: `ui/image-editor-design-file.md`, the `[NEW]` line named, the census file and this file deleted.

## What is open?
- The Page Design Studio's Canva start reading the same file for a page's real texts and pictures: asked of the owner after DF4.
