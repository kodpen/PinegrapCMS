---
name: image-editor-clip-keys
description: Quick clip edits in the Animate tool: the play bar's Split button, the S key, the scissors chip on a clip row, I and O trimming to the playhead, which clip Split takes
module: image_editor
---

## Where is it?
- The `clip` part (`pg_image_editor_clip.js`), the model's `CLIP` section (`clipSplitPick()`, `clipSplit()`) and ACCESS section (`timelineLetter()`, the `?` sheet's timeline group), the motion part's `motionKey()`. Tests: `tests/image_editor_clip_test.php`, `tests/image_editor_access_test.php`. The rest of clip editing: `image-editor-clip-timeline.md`.

## How is a clip split quickly?
- One method, `Editor.clipSplitHere(id)`, for every Split: the panel's Split clip, the play bar's Split (after Record, before the clock, only while the document holds a clip; scissors, its label from 1024 px of stage width, `pgie-mwide`), the S key in Animate, the chip and the phone's selection bar. #9972.
- Without an id `M.clipSplitPick(layers, selected, t)` chooses: the one selected clip when the playhead can split it, else the clip it can split lowest in the stack; never hidden or locked. Nothing to split shows `clipSplitNote` as an alert toast (a screen reader hears it).
- The chip: 24 px scissors on a clip row's lane at the playhead where it can split a clip of that row (`clipChipSet()`, moved with the tracks' playhead); not while playing, dragging a bar, at rest or on a phone (under 600 px).

## Which keys mark a clip?
- In the Animate tool I and O trim the selected clip's start and end to the playhead (`Editor.clipMark()`, `M.clipTrim` with no snapping, one undo step), the playhead inside the clip so the edge keeps 0.1 s of source, else the message `clipMarkNote`. #9234.
- J, K and L are the play transport (`image-editor-animation.md`), S splits (above). These six letters (`M.timelineLetter()`) override the Line, Image, Ellipse and Sticker tool letters while Animate is on, never while a field has the focus (`image-editor-access.md`).
