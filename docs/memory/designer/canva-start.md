---
name: canva-start
description: The Canva start of the New design window (ST6): exported PNG pages uploaded, kept as reference pictures under sketch pages, their colours offered for the variables; no Canva call, no PDF
module: canva_start
---

## What does it do?
- "Canva" in the New design window, between Figma and ZIP file (`pg_designer_start_kinds()`), pane title "Start from Canva pages" and one sentence: the pictures are a model to build on, not layers. No Canva connection or call (`workflow/decisions-made-product-107.md`).
- The pane (`pg_designer_canva_pane()`) holds the drop place; `assets/js/designer_canva.js` draws the rest. It is a start part like Figma's: loaded by `designer_home.js` the first time the start is chosen (`data-part`, `data-part-stamp` from `pg_designer_start_part_attrs()`), not a designer part or bundle.

## How is a picture taken?
- One file a request, multipart to `api.php` (designer › `canva_page`, `pg_canva_store_page()`); `api.php` reads a multipart body's fields as the request. The browser refuses first what it can tell (type by name, the site's single-file limit, more than `pg_canva_max_pages()` = 20); the server checks again.
- Server rules: `pg_upload_error_message()`, the kind from the first bytes (`pg_canva_file_kind()`: PNG or JPG; the stored name gets that extension), `getimagesize()`, then `pg_file_store_upload()` into the file manager's top folder (`pg_canva_folder()`) with the description "A Canva page, the model of a sketch page".
- A PDF is refused with its own reason: turning PDF pages into pictures needs Ghostscript behind ImageMagick, which runs the PDF's own program and which a host may not have. No part of the software rasterises PDF, so the drawing's PDF unfolding is not built.

## What happens to pages the person leaves?
- Architect (2026-11-26, agent channel request 2991): an abandoned start does what "Stop" does: it deletes what it stored itself and nothing uses. The bin, the cross on a sent upload, Cancel and the window's close delete this start's pages by the session's ids, never one a saved page uses (`start-abandoned-cleanup.md`, #11705).

## How are the colours measured?
- `pg_canva_measure()`: GD samples the stored picture over its own size (the upload cap may have shrunk it; hunt 57) to 64 pixels wide (nearest pixels, so edges give no blends; skipped over 40 megapixels or without GD), `pg_canva_colors_from_pixels()` boxes them four bits a channel and drops near white, near black and greys (HSL s < .25, l outside .12-.88).
- "Next" sends every page's list to designer › `canva_colors`: `pg_canva_pick_colors()` weighs each picture the same, joins colours closer than 64 ("redmean" distance) and gives at most `pg_canva_max_colors()` = 5. Brand colour is the first, Second colour the next; a press on a swatch changes it. Fonts are never taken: the third row reads "The site's default".

## What does "Create design" do?
- It writes the pages as the new design's sketch to `localStorage` `pg_proto_sketch_new` (one frame a page, side by side from x 640, each `pic: { src, h }` with h = 480 × height / width, 24 to 20,000) and opens `add_system_style.php?framework=bootstrap5&start=canva` with `&answers={brand, accent}`.
- `?start=canva` sets `sdDesign.autoSketch`: the editor opens the Prototype view zoomed to the first page, Sketch on (`_sdSketchStart()`).
- "Ask me the questions" hands the pane to DV4 through `window.sdStartAsk(pane, href, start, before)`; `pgStartQuestions.mount()` takes `start` (brand, accent) as its first answers.

## What happens on the first save?
- `_sdSketchAdopt(styleId)` moves the sketch kept under `pg_proto_sketch_new` to the new id's key. Before, a sketch drawn in an unsaved design was left behind under `new` once the design was saved.

## Tests?
- `tests/canva_start_test.php`: the kind, the refusals, a page's name, the ids a drop may delete, the colours of pixels and of several pictures, the row and the pane, and in Node the sketch the part writes and what the browser refuses. The upload, the drag order and the editor's opening were not run in a browser.
