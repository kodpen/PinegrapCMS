---
name: owner-notes-2026-11-25
description: Plan (owner 2026-11-25, after trying the dev site): larger frames in the designer's Focus control, a sketch built with what the site knows, a picture text's fit floor by its size; steps ON1 to ON3
priority: 7
---

## What is asked?
- Owner (2026-11-25), three things he met on the dev site: the Focus control's phone, tablet and desktop previews are too small to use; a page built from a sketch came out as the same sample texts and the same sample header; a slightly longer English translation of a picture's text was marked at once.
- His fourth note, the channel's Board tab not looking like its drawing, needs no step: the look (BM3, #11670) landed on 2026-11-25 at 18:57 UTC and the dev site's scripts were from 18:42 UTC.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- ON1 (S) — done, #11724 — after DO9 (#11681) has landed, which changes the same two scripts: the Focus control's frames, as board F5 of the image focus drawing draws them (`designer/designer-image-focus-drawing-frames.md`; the drawing wins, `workflow/screen-follows-drawing.md`). The sizes come from a pure function beside `PGImageFocus.shapes()` (rows, heights, the one-frame case), tested; `style_designer_focus.js` and the `.sd-fc-*` rules draw them.
  - The PR body carries the board's columns: what was built for each, what differs and why. `designer/image-focus-control.md` is corrected to what is built.
- ON2 (M) — done in #11699. "Build the page" says what the site knows, with Pinegrap AI off too (`designer/sketch-build.md`, `sketch-builder.md`):
  - A new shared "Header": the site's name as the brand, and as its links the Prototype view's pages in their order, by name, to their addresses from "Page details", at most six. A new "Footer": the site's name, the year, the same links. A design's own header and footer are used untouched, as today.
  - The intro: the page's title from "Page details" (else its name) as the heading, its description as the paragraph when there is one.
  - A section whose box has no typed word: its heading is its kind's name ("Features", "Gallery", "Contact") in place of the starter's sample line. A column copied to reach the sketch's count gets its number in its heading, so no two columns read the same.
  - Pure in `pg_sketch_builder.js` with what the caller hands it; tests. "Build again" keeps edited sections as today. Not waiting for SK6 of `plans/designer-sketch.md`, whose "Write the texts" writes real copy with AI on.
- ON3 (S) — done, #11698 — A picture text's fit floor by its size (`core/picture-text-translation-fit-letters.md`, the owner's words and the rule): `M.languageFit()` in `pg_image_editor_model.js` and its PHP twin `pg_picture_tr_language_fit()` take the source text's size and the picture's shorter side; tests for both with the same cases. `core/picture-text-translation-draw.md` and `picture-text-auto-server.md` are corrected.

## What does every step keep?
- No table, no column, no new right, no new button in a bar. The three steps share no file; ON1 and ON2 are k1's, ON3 is k2's (`workflow/coordinator-second-paused.md`).
- When the three have landed this file is deleted; nothing of it needs to stay but what the steps write into their topics' memory.
- Claim as `[plan:owner-notes-2026-11-25] <step> — <topic>`.
