---
name: figma-start-import
description: The Figma start's import (#11643): the four steps, pictures fetched with an upload's limits, shared components, variables, the report, the editor's tabs
module: figma_start
---

## What lists the Figma start?
- `pg_figma_import_start()` exists (`includes/fn/figma_start.php`, required at the end of `figma.php`), so `pg_designer_figma_start_on()` lists the start after Template.

## Which steps does the window drive?
- `api.php` designer › `figma_import` with `step` (designers only, page cache clean list). "Next" of the frame list (the `sd-figma-frames` event) runs `start`; then `frame` once per frame (index), `finish`, and `stop` on "Stop".
- `start`: one `GET /v1/files/:key/nodes?ids=` for the ticked frames (they must be in the file read kept in the session, at most 12), each converted (`figma-import-converter.md`), then one `GET /v1/files/:key/images` when there are image fills and one `GET /v1/images?format=svg` per hundred vectors: about three Tier 1 calls an import.
- The work is kept between steps in `data/temp/figma_<sha1(session id|import id)>.json`, not in the session; `pg_figma_import_ready()` reads it once and removes it.

## How are pictures and vectors fetched?
- `pg_figma_store_picture()`: the server downloads Figma's address (`pg_cloud_picker_download()`, never past `pg_upload_limits()['file_max']`), checks the first bytes (`pg_figma_picture_type()`: png, jpg, gif, webp, svg; anything else is not stored), passes an SVG through `pg_svg_sanitize()`, then stores with `pg_file_store_upload()`.
- At most `pg_figma_max_pictures()` (200) an import; the rest are report notes. One image fill used on several layers is stored once. Folder: `Figma/<file name>` (`pg_di_folder_path()`).
- An image node gets `src`, a box with an image fill `background-image:url("…")`; a failed picture leaves the place empty with a note.
- "Stop" deletes the fetched files (`pg_delete_file_record()`) and the shared rows, and removes the work file.

## Which component becomes shared?
- `pg_figma_shared_plan()`: a `componentId` met on two frames or more (twice on one frame is not enough). The first instance's subtree is the row's tree; every instance becomes a `shared_ref` (`sharedId`, `sharedName`, no children). A shared component inside another is a `shared_ref` there too, looked up only in the frame the tree came from (node ids are per frame).
- Rows have `category` `import:figma/<key>`, so leaving the design unpublished discards them like a template's (`pg_design_template_discard()`).

## Where do the variables come from?
- `pg_figma_found_variables()`: brand and second colour are the colours with a hue (saturation 25 % or more, lightness 15-85 %), a style named brand/primary first, then by use; up to six more colours; heading font of the largest text style, body font of the most used. Each row names its source: the style's name or "The frames' values".
- `pg_figma_variables_start()` sets brand, accent and the two fonts on the look's defaults: the model and stylesheet ride as `start_variables`, like the start questions' (`design-variables-questions.md`). A file with no colour or font skips the card.
- Not built: "Ask me the questions" on the card (the questions with these answers chosen).

## How does the report read?
- `finish` returns name, made/total, counts (pictures, vectors, shared, variables, notes, failed) and per frame `whole|notes|failed` with notes worded by `pg_figma_report_words()`; the window shows pills, a fold per frame, "Copy the report", "Open the design".

## How does the editor open the pages?
- "Open the design" leads to `add_system_style.php?framework=bootstrap5&start=figma&import=<id>`; `design.figma` (pages, shared, style_name) goes to `_pgFigmaOpen()`, which waits for the canvas and calls `_pgTemplateApply()`: unsaved tabs, Publish creates the design.
- Not built (drawing boards B8, B11, B12): the import going on with the window closed, the design card's line on the home, Figma's limit met half way waiting by itself.

Tests: `tests/figma_start_test.php`.
