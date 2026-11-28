---
name: office-pptx-preview
description: .pptx text reader and the preview's Text tab (PR #10585): title, text in reading order, pictures, speaker notes; limits, refusals; the Slides tab, Present and the canvas door elsewhere
module: office
---

## What does a .pptx show?
- Quick Look and the file window in the File Manager, and a channel's Preview, open a presentation on the tabs Slides (`office-pptx-slides-preview.md`, `office-pptx-present.md`) and Text.
- Text, since PR #10585, is the list of its slides: "Slide N" and its title, its text and pictures in reading order, a table's rows (cells apart by a tab), its speaker notes under "Speaker notes", a hidden slide marked "Hidden slide". Before it, it had no preview and the browser downloaded it.
- Not read by this list (the Slides tab draws what the design reader maps): slide layout and positions, the layout's and master's text, themes, fonts, colours, animation, transitions, charts, SmartArt, shapes without text, comments, audio and video.
- Forms: .pptx, .ppsx, .potx and .pptm (`pg_office_preview_kind()` gives `pptx`); `pg_office_kind()`, which every edit asks, gives '', so it is never edited in place; Edit in Artifact Canvas makes canvases from it beside the file (`office-pptx-present.md`). A channel still refuses .pptm uploads (`workspace/workspace-upload-refusals.md`). The binary .ppt has no preview.

## How is it read?
- `pg_office_pptx_read()` in `includes/fn/office.php`, no library: the presentation part named by `_rels/.rels` (ppt/presentation.xml when none), its `sldIdLst` through the presentation's relationships (the `r:id`, not the bare `id`) for the order, each slide a DOM through `pg_office_xml()`.
- Reading order is the shape tree's order (PowerPoint's own reading order): groups read in place, of `mc:AlternateContent` the Fallback. The first title or centred-title placeholder is the title and is not repeated in the text; empty paragraphs are left out; a paragraph keeps its level (`lvl`) and its line breaks.
- Notes: the body placeholder of the slide's notes page (`notesSlide` relationship).
- `pgOfficeView.render()` draws kind `pptx` the same in the file window and a channel: this list is the Text tab (`renderSlideList()`), beside the Slides tab of `office-pptx-slides-preview.md`; every text is set as text, a picture only from a `data:image/` URL.

## What are the limits?
- Pictures: the .docx preview's, through the shared `pg_office_image_url()`: 2 MB each, 8 MB together, png, jpeg, gif, bmp, webp; the rest show "[picture]" and the note "{1} picture(s) could not be shown." An EMF or WMF is lost.
- 500 slides (`PG_OFFICE_MAX_SLIDES`) and 600,000 characters of text (`PG_OFFICE_MAX_HTML`); past either the list is cut, with "Only the beginning is shown."
- The package limits of `office-files.md`; a slide, notes page or relationships part past 8 MB (`office-dom-ceiling.md`): "This file is too large to show here. Download it to open it." Not a zip, no presentation part, or a part not the size it declares: "The file could not be read."

## How is it tested?
- `tests/office_pptx_test.php` on presentations the test writes: order, titles, reading order, groups, a table, levels, line breaks, notes, a hidden slide, the picture limits, the slide limit, refusals. A python-pptx file read the same by hand.

Related: docs/memory/platform/office-files.md, docs/memory/platform/office-overview.md
