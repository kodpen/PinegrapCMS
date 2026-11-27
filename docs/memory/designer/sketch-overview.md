---
name: sketch-overview
description: Start file of the Prototype view's Sketch tool: decisions, parts by label (Sketch, New page, Build the page, Page details, Describe the page, Read the picture), which file holds what
---

## What is the Sketch tool?
- A drawing board in the canvas bar's Prototype mode: "New page" puts an empty frame, boxes are drawn in it, each box's chip says what it was read as (Header, Intro, Features, Cards, Text, Picture, Gallery, Form, Call to action, Footer, or a catalog, blog or booking widget), and "Build the page" turns the sketch into a draft page of starter blocks wearing the design's variables.
- Owner (2026-11-24): everything but the design itself is settled in the prototype step; the user draws the page and the system reads which layout is wanted, picking header, intro and the rest on its own.
- Owner (2026-11-24): rules read the drawing, Pinegrap AI helps where it is on; it works on every site without AI (`workflow/decisions-made-product-105.md`).
- Architect (2026-11-24): an unsure box is asked at "Build the page", never guessed in silence and never asked while drawing (`designer-sketch-drawing-check.md`).
- The sketch is kept with the page's draft (`props._sketch` in its root) and in the browser; no table or column was added. A change that needs one asks the architect first.
- Every action sits in the selected sketch page's head ("Build the page" and "More") and in the command palette; no button enters the page bar, the canvas bar or a panel's head (`designer-sketch-drawing.md`).

## Which file holds what?
- Drawing boxes: the Sketch switch, "New page", frames `k:<n>`, snapping, chips, typed headings, keys, undo, browser storage: `prototype-sketch-tool.md`.
- Reading: `PGSketchReader`, rules, unsure reasons: `sketch-reader.md`.
- Building from sections: `PGSketchBuilder`, kind to block, columns, the section rhythm variable: `sketch-builder.md`; the starters it gets and pages built before #11794: `sketch-build-starters.md`.
- "Build the page" (part `sketchbuild`, chip marks, the list of boxes to check, the draft tab, `props._sketch`): `sketch-build.md`; "Build again": `sketch-build-again.md`.
- "Page details" (seven rows, `sketch_details` endpoint): `sketch-page-details.md`.
- "Describe the page" and "Write the texts" (part `sketchai`, `sketch_ai` endpoint, limits): `sketch-ai.md`.
- The reference picture under a frame: `prototype-sketch-picture.md`; "Read the picture" (browser's model, nothing sent): `sketch-read-picture.md`.
- Speed and touch, measured with `tests/js/sketch_feel.js`: `sketch-feel.md`.
- How each part was drawn (sheets in `designer-sketch-drawing/`, boards per step): `designer-sketch-drawing.md` and its `designer-sketch-drawing-*` files.
- The Prototype view it lives in: `prototype-view.md`, its rules `prototype-view-rules.md`.

## Which PRs built it?
- Reader #11493, tool #11497, builder #11532, page details #11545, picture #11557, Read the picture #11584, Build the page #11642, AI actions #11682, site header and footer #11699, feel #11749, starters fix #11794.

## How is a new part added?
- A new box kind goes into `SD_SKETCH_KINDS`, the reader's `KINDS` and `blockFor()` in the builder together, with its chip label through `_sdT()` and a test in `tests/sketch_reader_test.php` and `tests/sketch_builder_test.php`.
- A new action is a row in `_sdSketchMoreRows()` and the command palette, never a bar button; code that is not needed on every page goes into a designer part loaded on first use, as `sketchbuild` and `sketchai` are.
- Anything sent to a model goes only through Pinegrap AI with its switch on (`sketch-ai.md`).
