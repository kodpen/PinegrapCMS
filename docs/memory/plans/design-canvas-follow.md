---
name: design-canvas-follow
description: Plan (owner 2026-11-16): Follow in the Design Canvas's Animate tool: a text, vector or other layer moves with an area chosen on a video, by a tracker of our own that writes plain keyframes; FW1 to FW3
priority: 6
---

## What is asked?
- Owner (2026-11-16): "bir yazı veya vektör koyup takip et dediğimizde takip için seçtiğimiz alan ile hareket edebilir mi?" Yes: nothing like it exists today; the nearest is Recording a move, done by hand (`ui/image-editor-record.md`).
- The features are F1 to F16 in `plans/design-canvas-follow-features.md`, the steps in `plans/design-canvas-follow-steps.md`. A step's PR body names each feature it builds and where it is; one it leaves out is named with the reason.

## How does it work, in short?
- In Animate, with one layer selected over a video, Follow asks for a box over the thing to follow. The video's frames from the playhead on are searched for that patch; the layer gets keyframes that move it by as much as the patch moved.
- The result is ordinary keyframes in `anim.keys` (`ui/image-editor-keyframes.md`), thinned as a recorded move is: the stage, the frames, every export and the Path tab show and edit them with no new code, and nothing new is kept in the document.

## What binds it?
- Own code, no library, no trained model, nothing sent to a service (`ui/image-editor-overview.md`; owner 2026-11-03 on Select subject, `ui/image-editor-subject.md`). The tracker is template matching, in a worker of its own bundle as Select subject's cut is.
- No schema change, no new document field, labels through `lang()`, redaction layers never move.
- The fix of issue #9950 lands before FW2: Follow reads a clip's frames through the same video source.

## What is left out of the first version?
- The followed thing's size and turn (the layer only moves), perspective (a sign on a wall seen from the side), a mask that follows a shape.
- Following backward from the playhead, several areas at once, a thing named in words (that needs a model).
- A clip that itself moves by keyframes or a preset is followed as if it stood still.
- Keeping the link: the keys are plain. After the clip is trimmed, moved, split or its speed changed the layer does not follow again by itself; Follow is pressed again. A note in the Path tab says so (F13).
- Across a cut: a split makes another clip, Follow stops at its clip's end.

## Whose look?
- FW2 (the armed stage, the box, the progress pill, the lost message, the Path tab's button) is the owner's: a new command and a new design. Pictures in the agent channel at 1440 and 1024, light and dark: armed with its hint, the box being drawn, in progress, done with the path over the video, lost; and one short recording of a run on the fixture. The architect brings one sheet to him; the step is not marked ready before his look.
- FW3's phone screens are the architect's look (owner's channel message 2645) where they repeat Record's pill and folded sheet; anything new there goes to the owner with FW2's sheet or after it.
- The architect's readings, not his words: the box drawn by hand as "the area chosen", forward only, the layer keeping its place at the playhead as the start.

## What must a worker not do?
- No tracker from a package, no WebAssembly build of one, no model file. A method beyond F6's that seems needed (features, optical flow) is asked of the architect first, with the fixture's numbers.
- No field such as `anim.follow` in the document; no change to how keys play.
