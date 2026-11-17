---
name: picture-text-translation-steps-2
description: Plan picture-text-translation, steps PT5 to PT7 and their order: the drawing queue, the file doors of a language's picture, the close-out
---

## Which steps follow PT4?
- PT5 (S) after PT4: the queue. Done (PR #10266; `core/picture-text-translation-queue.md`). "Draw the waiting pictures" on the Pictures group opens a page that draws them one after another in this browser, as `assets/js/workspace_canvas_picture.js` draws an agent's canvas, with the count left and Stop. Pictures: running and finished.

- PT6 (S) after PT4: the file doors. A language's picture follows its source on a move, goes with it on a delete, is never swapped while the source is binned; Disk Usage counts it; a daily sweep removes a picture whose source is gone. Tests for each door.

- PT7 (S) close-out: every decided line checked against the code with its PR; `core/` and `ui/` memory files for the store type, the pass and the list; this plan's files and the census deleted. The PR body of PT4 names the `[NEW]` changelog line.

## Which steps wait for each other?
- PT1 first. PT2 and PT3 side by side. PT4 after both, then PT4b, PT5 and PT6 in any order, one at a time on the editor's files.
