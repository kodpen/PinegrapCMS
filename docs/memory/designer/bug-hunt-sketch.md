---
name: bug-hunt-sketch
description: The Sketch tool's bug hunt (2026-11-26): the eight faults fixed in "Build again", "Write the texts", "Describe the page", the picture proposal and "Page details", and what was read clean
module: sketch_ai
---

## What was hunted?
- The Sketch tool's parts: `assets/js/style_designer_sketchbuild.js`, `style_designer_sketchai.js`, `style_designer_sketchdetails.js`, `style_designer_sketchread.js`, `includes/fn/sketch_ai.php`. The core's hosts in `style_designer.src.js` were read, not changed.
- Looked at: boxes the reader is unsure of, "Build again" over edited nodes, a failed or half AI answer, undo after a build.

## What did "Build again" get wrong?
- An edited section it kept took the edited node's fingerprint as its "built" one, so the next box change rebuilt it without asking and dropped the edits. Now it keeps the built fingerprint (`sketch-build-again.md`).
- A section duplicated in Design vanished on "Build again": the copy carries the same `_sketchId`. Now the copy stays after it.
- After an undo in Design of a build, "Build again" stayed dimmed ("The sketch has not changed…"). Now it compares with the sketch kept in the draft's root.

## What did "Write the texts" and "Describe the page" get wrong?
- A section with more than 24 texts, or a text of only Markdown marks (`**`), always failed: the server keeps at most `PG_SKETCH_AI_ITEMS` and drops empty ones, so the counts never matched. `_saItems()` now sends only what the server keeps.
- A refused request (the day's drafts used, asked too quickly) said only "could not be …". The server's message now leads the rose line (`_saWhy()`); a generic message is not said twice.
- The texts' line kept its "Undo" after later edits of the page; it undid that later edit. The line now goes once the page's tree differs from what the texts left (`_saUndo()`), checked again on the click.
- Any AI line removed the "Read the picture" proposal's foot with "Keep" and "Discard". The proposal's foot is marked `.sd-sketch-read-foot` and the AI line waits under it.

## What did "Page details" get wrong?
- Its head said "· sketch page, not built yet" on a built page; it now says "· draft" when the sketch page carries `built` (drawing D1).

## What was read clean or left?
- The unsure list (settle, keys, Cancel keeping settled kinds as drawn), `plan()`'s sorting, the History snapshot before "Build again", the reader's caching, `_saBoxes()`, `_srBoxes()`, the server's checks and limits.
- Not fixed: the texts request sends no section `kind` (the server reads one); "Undo" of "Describe the page" undoes the sketch's last step even when another page changed since; a failed build may leave the new header, footer or widget rows it made. Not run in a browser.

Related: docs/memory/designer/sketch-ai.md, docs/memory/designer/sketch-build-again.md
