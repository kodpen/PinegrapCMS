---
name: designer-canvas-mirror
description: The canvas mirror, designer part 'mirror' (SS1 #11535): a frame filled from the canvas's markup without the editor, redrawn when the hand rests; its pure module PGMirrorSchedule
---

## What is a mirror?
- A picture of the draft at another width (plan `designer-screens-side-by-side.md`): an `iframe.sd-mirror-frame` with `sandbox="allow-same-origin"` (no script, form or navigation), its document written once in standards mode; clicks, submits and drags in it are stopped.
- Filled from the canvas document itself: `<html>` attributes (colour mode, language; the canvas zoom dropped), the head (styles, `<base>`, links) when it changed, the body cloned. So it draws the canvas's samples and colour mode with no visitor script and no call to the site.
- Taken out of the clone: `script`, the Outline lines and the snap grid (`sd-outline-grid`, `sd-grid-overlay` on the body), the editor's chrome (`.sd-tb`, `.sd-persistent-note`, `.sd-issue-badge`, `.sd-chip-row`, `.sd-dz`, `.sd-align-guide`), its state classes (`sd-selected`, `sd-drop-hl`, `sd-tb-below`…), `contenteditable`, `draggable`, `spellcheck`, every `on*` attribute; an embedded `iframe` / `object` / `embed` gets `about:blank`, media loses `autoplay`.

## When is it drawn again?
- `window.PGMirrorSchedule` (`assets/js/pg_mirror_schedule.js`, pure): `step(state, kind, now)` with `change`, `key`, `down`, `up`, `drawn`; `due(state)` is 300 ms (`REST`) after the last change, key or release, never while a pointer is held. A pointer held 15 s (`HOLD_MAX`) with nothing happening counts as let go.
- `matters(rec)` says which canvas changes count: the editor's chrome and state classes, and the editing attributes, do not, so hovering (toolbar filled and placed) draws nothing. The part judges a change inside chrome by the chrome's class (`CHROME_PARTS`).
- `fit(width, boxW, boxH)`: the scale (at most 1, three decimals) and the frame's height before scaling.

## How is it loaded and what runs while it is off?
- The part `mirror` (`assets/js/style_designer_mirror.js`, bundle `style_designer_mirror.min.js` = the pure module, then the part; `pg_designer_parts()`), handed to every editor (`'when' => 'always'`) and run the first time the switch is on. Entry points: `side(cfg)`, `open(host, width)` (answers an id, draws at once), `width(id, w)`, `resize()`, `close(id)` (all with no id), `rebind()`, `draw()`, `count()`.
- Only while a mirror is open: a MutationObserver on the canvas document, capture listeners for pointer, drag and keys on the canvas and the editor document, and `blur` on the window. The last `close()` removes them all and the timer.
- The core holds `_sdMirrorRebind()`, called when `createCanvasIframe()` writes a new canvas document; it does nothing until the part has loaded.
- The row, its heads and lists: `designer-side-by-side.md`.

## What was measured?
- Core `style_designer.min.js`: 3,002,363 to 3,002,460 bytes (786,640 to 786,697 gzip) at SS1; the part's bundle 19.9 KB (7.6 KB gzip) after SS3 (#11676), loaded on first use only.
- Node 22, 4 cores: `step()` + `due()` 30 ns, `matters()` 0.31 µs per mutation record.
- Sandbox Chromium, a 1,539-element canvas body, two mirrors (375, 768): one redraw median 13.6 ms, worst 30.9 ms of 25, paid only at rest; 100 canvas text changes with the observer on 0.10 ms.
- Tests: `tests/designer_mirror_test.php` (scheduling, the widths, the room, the size list).
