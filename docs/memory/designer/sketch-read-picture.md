---
name: sketch-read-picture
description: "Read the picture" of the Sketch tool (SK7b): the reference picture read into proposed boxes by the browser's model, nothing sent; row, first use, busy, Keep, Discard
---

## Which model reads the picture?
- Only the browser's (Prompt API, image input), as for alt text (`ui/ai-write-alt-text.md`): the picture is fetched and read in the browser, nothing goes to the server, no endpoint. Architect, channel message #13376; owner (2026-11-25): Pinegrap AI is not yet good enough for pictures.
- `PinegrapAiWrite.readImage({ image, text, schema, signal })` (assets/js/pg_ai_write.js) creates the model in the click, fetches the picture with `loadImage()` and answers the model's JSON. It works only after `imageReady()` resolved true.

## When is the row there?
- "Read the picture" is the last row under the caption "Reference picture" in a sketch page's "More" (`_sdSketchMoreRows()`), only while the picture is shown and `_sdProto.sk.reads` is true.
- `_sdSketchReadReady()` asks `imageReady()` once, when the Sketch switch first goes on. Without "Write with AI" on the screen (`window.PinegrapAiWrite` missing) or a model that takes images, there is no row.

## Where is the code?
- The part `sketchread` (`assets/js/style_designer_sketchread.js`, `pg_designer_parts()`), loaded on the row's first click. The core hands it `_sdSketchReadHost()` (page, redraw, announce, limits, keep). `_sdSketchFill()` calls the part's `fill()` and `_sdSketchKey()` its `escape()`, both only once it has loaded.
- Core +1.0 KB minified (+0.3 KB gzip); the part 6.6 KB (2.8 KB gzip) on first use (#11584).

## How does a reading run?
- First use in this browser (`localStorage` `pg_sketch_read_seen`): a foot under the frame reads "“Read the picture” reads this picture with your browser's built-in AI. It is sent nowhere." with "Cancel" and "Read the picture". Later uses start at once.
- Busy: the frame's head shows a spinner, "Reading the picture…" and "Stop". Stop or Escape aborts the model.
- The model is asked for at most 40 boxes in percent of the picture; `_srBoxes()` makes them frame units over the picture's height (`pic.h`), on the 8-unit grid, at least 24, a heading of at most 80 characters, with repeats dropped.
- The proposal is drawn in the accent over the picture, without chips. The foot counts it ("9 boxes proposed from the picture", or "… “Keep” replaces the 3 on the page; one Undo brings them back.") with "Discard" and "Keep".
- "Keep" replaces the page's boxes with new ones (`kind: ''`, so the reader reads them) in one step of the sketch's history. "Discard" or Escape drops them.
- Nothing found: the toast "No boxes could be read from this picture." Failure: the foot says "The picture could not be read. Nothing was changed." with "Try again".

## How was it checked?
- `tests/designer_sketch_read_test.php` (`_srBoxes()`, the count line, the wiring) in Node, and `node --check`. Not run with a model: the container's Chromium has no Prompt API.
