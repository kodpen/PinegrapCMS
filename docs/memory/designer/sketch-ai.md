---
name: sketch-ai
description: The Sketch tool's two Pinegrap AI actions (SK6): "Describe the page" and "Write the texts" in "More", when they show, the sketch_ai endpoint, limits, what is sent, undo, stop
module: sketch_ai
---

## When are the actions there?
- Under the caption "Pinegrap AI" in a sketch page's "More" (`_sdSketchAiRows()` in `_sdSketchMoreRows()`), only while `sdDesign.sketchAi` is true: a designer, on a site where "Write with AI" is on and Pinegrap AI writes (`pg_ai_write_engine() === 'pinegrap'`). No bar button, no palette row.
- "Describe the page" is always there; "Write the texts" only once the page is built and its draft tab exists. While texts are written, "Stop writing the texts" is the first row and "Write the texts" is dimmed (a menu row's `off`).
- An answer with `off: true` (Pinegrap AI switched off meanwhile) closes the window, stops the run, toasts "Pinegrap AI is off." and takes the rows away (`host.off()`).

## Where is the code?
- The part `sketchai` (`includes/designer_parts/sketchai.php`, `when: designer`, `assets/js/style_designer_sketchai.js`), loaded on the first row chosen; the core hands it `_sdSketchAiHost()` (the read host plus the built page, its tree, one undo step of its tab, the post). Its `fill()` runs after sketchbuild's, its `escape()` from `_sdSketchKey()`.
- Server: `designer/sketch_ai` (`includes/panel/designer.php`, designers only, in the page cache's read-only list) calls `pg_sketch_ai_request()` (`includes/fn/sketch_ai.php`). It writes nothing; one request is one `pg_ai_chat()` call.

## Which limits apply?
- Those of "Write with AI" (`ui/ai-write.md`): `pg_ai_write_problem()`, the site's daily allowance (one request counts one draft), the session closed before the call. Its own pace: 20 a minute a person (`waf_rate` scope `sketch_ai`), as a page's texts are one request a section.
- Describe: the words at most 2,000 characters; at most 40 boxes; in the pages' language (`pg_ai_write_site_language()`).

## How does "Describe the page" run?
- The window as drawn (`designer-sketch-drawing-ai.md`): the field keeps the last words sent for that page (in memory); an amber line names the boxes it replaces; Ctrl+Enter draws, Escape closes.
- The model answers boxes on a page 100 units wide (`pg_sketch_ai_boxes()`); the part makes them frame units on the grid (`_saBoxes()`), at least `SD_SKETCH.min`. They replace the page's boxes through the read host's `keep()`: `kind: ''`, one sketch undo step, the reader gives the kinds. The line "Sketch drawn from your words: n boxes." has "Undo" (the sketch's undo); it goes once the boxes change.
- Busy: spinner, "Drawing the sketch…" and "Stop" in the head, the boxes dimmed and taking no pointer. Stop or Escape drops the answer: "Stopped. The page is as it was."

## How does "Write the texts" run?
- Sections of the built draft whose node still has the fingerprint it was built with (`props._sketch.secs[id].fp`, sketchbuild's `nodeFp()`) are sample; others count as edited. Shared header, footer and widgets (`shared_ref`) are left out.
- One request a section: its headings, paragraphs and links with words, in order (`_saItems()`, at most 24), the section's name and the page's name, title and description. The answer must give the same count (`pg_sketch_ai_texts()`), plain and cut (heading 80, link 40, paragraph 600). A section edited while it was asked is left as it is.
- Before the first text one undo step of the page's tab is taken (`saveState()`, or the tab's own `undoStack` when not open); "Undo" on the line takes all texts back. The fingerprints are not updated: a written section counts as edited, so "Build again" keeps it and a second run leaves it.
- Head: "Writing the texts… 3 of 7" in place of "Draft". Stop keeps what is written. A failed answer stops with "The texts could not be written. n sections were written, …" and "Try again".

## What was not checked?
- Not run with a live Pinegrap AI or a browser; pure parts in `tests/sketch_ai_test.php` (PHP and Node).
