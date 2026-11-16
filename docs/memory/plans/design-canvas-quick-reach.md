---
name: design-canvas-quick-reach
description: Plan (owner 2026-11-16): the Design Canvas's commands closer to hand: Split on the play bar, selecting from any tool, a bar over the selected object on the desktop, motion presets from it; QR1 to QR4
priority: 6
---

## What is asked?
- Owner (2026-11-16, on the Animate tool with a video): "video animasyon alanında hızlı bölme alanı olmalı"; "tuvalin menüleri arası geçişler çok zorlayıcı olabiliyor seçme araçları tıklandığında nesnenin üzerinde toolbar olarak çıkabilir mi (mevcut işleme yaparken başka öğeyi seçmek için her seferinde seç aracına gitmek gerekiyor sağ panel gidiyor o yüzden seç daha hızlı erişilir olmalı.)"; "hareket animasyonları da keza animasyon ekranında daha hızlı erişilebilir olmalı".
- The features are R1 to R12 in `plans/design-canvas-quick-reach-features.md` and R13 to R24 in `plans/design-canvas-quick-reach-features-2.md`, the steps in `plans/design-canvas-quick-reach-steps.md`. A step's PR body names each feature it builds and where it is; one it leaves out is named with the reason.

## What did the check of the code find?
- Split clip is one button at the foot of the clip panel's Clip tab (`clipSplitAt()`), and in the selection bar where the panel is a sheet. The play bar (`motionBar()`: Play, Record, clock, scrub, Repeat, length) has none and no key splits.
- The selection bar (`ui/image-editor-actions.md`) is hidden on the desktop by the stylesheet, so Select's commands are only in Select's panel: using one swaps the panel of the tool at work.
- A press on a layer selects it only in the tools of `Editor.selectTools`. The Text tool starts a new text on any layer that is not a text; a tool with no press of its own on the stage (no `Editor.pointers` entry) does nothing; Pen, Scissors, Shape builder, Warp and Free deformation have presses of their own.
- In Animate a press on the stage first drops the playhead to rest (`motionDown()`), so picking another layer loses the moment being worked on.
- A video's motion presets are three presses deep (the clip panel's Motion tab, then In, Loop or Out, then the grid).

## What binds it?
- `ui/image-editor-overview.md`, `ui/image-editor-animation.md`: own code, no library, labels through `lang()`, redaction layers never move. No schema change and nothing new in the document: these are ways to reach commands that exist.
- The phone's selection bar and rows stay as approved (`workflow/decisions-made-product-28.md`, `workflow/decisions-made-product-49.md`): every change here is for where the panel is not a sheet, but R2, R3 and R5.
- The fix of issue #9950 (a video stutters while it plays) lands before QR1: both enter `pg_image_editor_clip.js`.

## What is left out?
- Splitting every row at once, a blade tool that cuts where it is pressed, Split on the phone's play bar (the selection bar has it).
- Moving Select's whole panel into the bar: the bar holds a few commands and More, which opens the context menu's list.
- A way to move or hide the bar, new motion kinds, a change of the panel's tabs.

## Whose look?
- QR1 (the Split button, the chip on the row) and QR4 (a row's Motion button) are the architect's (owner's channel message 2645): one more button of a kind the bar and the row have.
- QR3 (the bar over the object, the Motion popover) is the owner's: a new design on a screen he uses. Pictures in the agent channel at 1440 and 1024, light and dark: one text, one video in Animate with the popover open, several layers, a layer at the stage's top edge (the bar below it), a locked layer. The architect brings one sheet to him; the step is not marked ready before his look.
- The architect's readings, not his words: which commands the bar holds, Ctrl or Cmd as the press that selects, S as the key, the popover's rows.
