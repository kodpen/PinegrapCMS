---
name: design-canvas-quick-reach-features
description: Plan design-canvas-quick-reach, features R1 to R12: Split on the play bar, its key and chip, which clip it takes; selecting from any tool without losing the tool, its panel or the playhead
---

## Quick split (QR1)
- R1 A Split button on the play bar after Record, before the clock: scissors, its label beside it from 1024 px of stage width, a title with the key. The clip part wraps `motionBar()` as the record part does.
- R2 Which clip: a pure `M.clipSplitPick(layers, selected, t)` answers one clip id or null. The one selected clip when the playhead can split it (0.1 s of source on both sides, `M.clipSplit()`'s rule); else the clip the playhead can split, and with clips on several rows the one lowest in the stack.
- R3 With nothing to split the button stays pressable and shows the message the panel shows today (`clipSplitNote`), also said to a screen reader; it is never a dead button without a reason.
- R4 The key S splits while Animate is on, as I, O, J, K and L override a tool letter there; not while a field has the focus. The `?` sheet lists it; `ui/image-editor-clip-timeline.md` and the access file say it.
- R5 A chip with scissors on the playhead where it crosses a clip's bar that can be split there (the tracks' clip row): a press splits that clip; 24 px, not drawn while playing or while a bar is dragged. Not on the phone's rows.
- R6 One method for the panel's button, the bar's button, the key, the chip and the phone's selection bar. After a split, as today: the second part selected, the playhead where it was, one undo step. The panel's button stays.

## Selecting from any tool (QR2)
- R7 The census, as a table in QR2's PR body before any code: for every tool and mode of the rail, what a plain press on a layer other than the selection does today, what a click without a drag does, and what the panel then shows. From the code (`Editor.pointers`, each tool's `down`), not from a browser.
- R8 Ctrl held (Cmd on a Mac) makes the press Select's press (`startDrag()`) in every tool: it selects the layer under it, Shift adds, a drag moves it or draws the box. The tool and its panel stay. Not while cropping, typing a text, placing a path or a polygon, or recording a move. A tool that already reads Ctrl or Cmd on its press is named in the PR with what it keeps.
- R9 While Ctrl or Cmd is held the selection's box and handles show in every tool, and the pointer is Select's arrow.
- R10 In Animate a click on another layer keeps the playhead: the layer is selected and the panel shows its motion at that moment. Only a drag that moves or sizes a layer at rest drops the playhead first, as today. The keyframe press (`keyHit()`) and Record keep their rules.
- R11 The Text tool: a click on a layer that is not a text selects it (the Text panel then shows nothing to set, as with nothing selected); a new text starts on an empty spot or on the picture, as today.
- R12 Pen, Warp, Free deformation, Scissors and Shape builder: a plain click on another layer of the kind the tool works on takes it without leaving the tool, where the census finds it does not already. The Layers list selects in every tool and the tool stays. What the hint under the stage says for a tool is kept true (`Editor.hints`).

## What does QR2 test?
- A pure `M.pressSelects(tool, state, e)` (or the name the worker gives it) answers whether a press is Select's, tested for each tool with and without the key and in each state that refuses it (R8), red first.
- The rest is pointer handling a unit test does not reach: the PR says what was tried by hand and what was not.
