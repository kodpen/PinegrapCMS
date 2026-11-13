---
name: image-editor-phone-rows
description: The Animate tool on a phone with a video (V10): the rows in the sheet under the play bar, names above lanes, tall clip row with finger-size grips, folded sounds, Split in the selection bar
module: image_editor
---

## Where is it?
- Part `phone` (`pg_image_editor_phone.js`, bundled after `upload`, before `actions` and `menu`, which stays last), the model's `PHONE` section (`phoneRows()`, `phoneSoundLine()`), labels in `pg_image_editor_strings_phone()`, rules at the end of `pg_image_editor.src.css`, tests in `tests/image_editor_phone_test.php`. Added in V10 of `plans/image-editor-video-steps.md`. Screen 42-phone-video.

## When do the rows show?
- Under 600 px wide (a phone held upright, where the play bar is the sheet's first row), in the Animate tool, once the document holds a shown video layer (`M.phoneRows()`). Without a clip, and held sideways (the sheet over the stage's right, the stage only 390 px tall), the panel's delay and duration sliders and the Sounds list do it, as before.
- On a phone `tracksShown()` and `soundsShown()` answer through the part; the stage's dock never shows there (`renderMotion()`), so the nodes go into the sheet: `renderPanel()` puts a `.pgie-phrows` right after the play bar (`.pgie-mbar`), with the tracks' node and the sound rows' node. Drags, snapping and undo are the tracks', clip and sound parts' own.

## What do they look like?
- One list: one ruler, each name above its lane (`--pgie-trname: 0`), the playhead across all rows, no row scroll of its own (the sheet scrolls). The clip's lane 56 px tall; trim grips 14 px wide with a 44 px target (`::after`); no tip beside the bar.
- The sounds folded into one line, "Sound · 2 rows" ("Sound · 1 row"), a press opens or closes them (`phoneSoundsOpen`, per editor, closed at start). Add sound stays the last row and in the Sounds list.

## Why is the sheet kept under a press?
- A press on another layer's, clip's or sound's bar selects it and calls `renderPanel()` before the drag starts; in the sheet that replaced the bar under the finger and the drag never ended. The part wraps `trackDown()`, `clipDown()` and `soundDown()` (`phoneHold`) and skips `renderPanel()` while one of them runs or a drag is on; the release draws everything again. A press while playing only pauses.
- Drawn again, the sheet keeps its scroll.

## What does the selection bar add?
- Split (`actSplit`, "Split"), before Duplicate and Delete, when one video layer is selected in the Animate tool (`actionState().split`); off when it is locked. It calls `clipSplitAt()` (`image-editor-clip-timeline.md`). The context menu has no Split (`menuList()` passes no `split`).

## How was it checked?
- Sandbox, 390×844 touch (#8854): a WebM clip and a WAV on a new canvas; the rows under the play bar, the end grip dragged (8.0 → 6.9 s), the sounds opened, a sound's and the clip's bar dragged while another was picked (both drags ended, one change each), Split from the bar at 2 s and undo. 844×390: no rows, as before; 1280×800: the dock's tracks, no rows.
