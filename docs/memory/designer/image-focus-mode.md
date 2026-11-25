---
name: image-focus-mode
description: "Set focus" on the canvas (IF3): the toolbar button, the mode that drags a picture under its frame, its keys, what it writes, how it is loaded, tested and measured
module: image_focus
---

## Where is "Set focus"?
- One icon button (`bi-bullseye`, tooltip "Set focus", class `sd-tb-focus-btn`) in the selected element's canvas toolbar, before Delete, built in the lazy toolbar fill. No bar button.
- Shown where `_sdFocusKindOf(node)` finds a cut picture: an Image with a picture first, else a background picture sized "Cover" or drawn once at its own size (`_sdFocusTakes()`), and only on an element the person may edit and that is not locked.

## What does the mode do?
- `_sdFocusModeOpen(node, kind)` builds the same host as the Focus control (`_sdFocusHost(null, node, kind)` plus `doc`) and calls the part's `mode(host)`.
- Drawn in the canvas document, position fixed: a pale layer over the page, the whole picture dimmed, the frame with the picture at full strength, a grab mark, and the line "Drag the picture under its frame. Arrows move it by 1%.", the value, "Cancel", "Keep".
- The picture follows the pointer (a drag to the right shows more of its left side); a side the frame does not cut keeps its value. The arrows move the picture by 1 % the same way. Enter, "Keep", a press outside the picture or anywhere in the designer outside the canvas keeps; Escape and "Cancel" put back the element's own inline position.
- A screen where the picture is not cut ("Contain", "Fill", a small picture at its own size) says "This screen shows the whole picture." and has nothing to drag.
- Nothing else on the canvas can be pressed; keys go to the mode first in both documents; the wheel does not scroll. Every listener is removed when it ends.

## What does it write?
- The value of the screen the canvas bar shows, through the host's `commit()`: one undo step, only when the picture was moved (a typed value stays as typed otherwise). While dragging, `live()` moves the canvas element and the Options panel's Focus control of the same element follows.
- It starts at the screen's value, else where the canvas draws the picture now (its computed position), else the middle.

## How is it built and tested?
- Pure helpers in `pg_image_focus.js`: `drawn(fit, fw, fh, nw, nh)`, `start()`, `drag()`, `nudge()`, `offset()`. The mode is in the part `focus` (`style_designer_focus.js`), loaded on first use.
- `tests/designer_image_focus_mode_test.php`: sizes and cuts, start, drag, arrows, offset, wiring. A Playwright run on the built part (PR #11609) checked drag, Escape, Enter, a press outside, and the uncut line; not run inside the full designer.
- Measured (PR #11609): core `style_designer.min.js` +0.9 KB (+0.26 KB gzip); the part 9.0 → 16.6 KB (3.8 → 6.4 KB gzip), loaded on first use. Listeners while nothing is selected: unchanged (none added).

Related: docs/memory/designer/image-focus-control.md, docs/memory/designer/image-focus-value.md, docs/memory/designer/designer-image-focus-drawing.md
