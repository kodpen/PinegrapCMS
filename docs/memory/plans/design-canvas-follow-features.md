---
name: design-canvas-follow-features
description: Plan design-canvas-follow, features F1 to F16: where Follow is offered, the box, the frames read, the tracker's method and its limits, the keys written, progress, cancel, lost, the tests
---

## The command (FW2)
- F1 Offered in Animate for one selected layer that may move and is not a video: a button "Follow" in the Path tab beside "Recording a move". With no video showing at the playhead it is pressable and says why ("Move the playhead onto a video first").
- F2 Arming: the stage shows the hint "Drag a box over what to follow"; a drag draws the box (at least 12 px a side on the screen); letting go starts. Escape, another tool or a press on Follow again cancels. While armed a press never selects or moves a layer.
- F3 The clip is the topmost video showing under the box's centre at the playhead; none there: the message of F1.
- F4 The span: from the playhead to the earliest of the clip's end on the timeline, the layer's own end (`anim.out.at`) and the document's end. Under 0.2 s: a message, nothing changed.
- F5 Frames come from a source of its own for that file (as the export's and the filmstrip's do, `ui/image-editor-clip-export.md`), never the stage's decoder: each source frame of the span in order (`M.clipAt()` gives the source time; speed counted), at most 30 a second, drawn 320 px on the longer side, as grey.

## The tracker (FW1)
- F6 Method: the box's patch at the first frame is the template. In each next frame the best place is searched round the last one by zero-mean normalised cross-correlation, within the larger of 16 px and half the box's longer side; the peak is refined below a pixel by a parabola through its neighbours.
- F7 The template is renewed slowly (a small share of the found patch mixed in when the score is high) so a slow change of light or angle is kept up with; the first template is kept as the check against drift. The starting numbers are the worker's, tuned on F15's cases and named in the PR.
- F8 Lost: the score under the floor for five frames in a row, or the box mostly outside the frame. Tracking stops at the last good frame.
- F9 It runs in a worker (`assets/js/pg_image_editor_track.js`, its own bundle and `config` address, as `pg_image_editor_subject.js`); on the page in slices where a site's policy refuses the worker.

## The result (FW2)
- F10 Samples `{ t, x, y }`: the layer keeps the place it shows at the playhead and moves by what the patch's centre moved since, from source pixels to document units through the clip's box (its width and height over the source's, its turn, its flips). A pure function in the model, tested.
- F11 Keys through `M.recordThin()` and `M.recordApply()`: keys inside the span replaced, the others kept, one undo step holding the document before.
- F12 Progress: a pill over the stage with "Following…", the share done and Cancel; the path grows on the stage as samples come. Cancel leaves the document as it was. Done: the playhead back at the start, the layer selected, the Path tab open.
- F13 Lost keeps the keys up to there and says "Lost it at 0:03.4. The move up to there is kept; move the playhead there and follow again." The Path tab notes that a clip edited later is not followed again by itself.
- F14 Speed: a 10 s clip at 30 frames a second in about 10 s or less on the fixture; the PR gives the measured time and where it goes (decode, grey, match).

## The tests
- F15 `tests/image_editor_follow_test.php` under Node, red first: frames drawn in the test (a textured square on a textured ground) moving straight and on a curve, with noise and a slow change of brightness: the found centre within 1.5 px; the square covered or gone: lost at that frame; F10's mapping for a turned and a flipped clip; samples to keys.
- F16 A browser run on `tests/fixtures/video/` (the fixture of issue #9950's fix, or one added here): what was followed, the time, and what was not tried, in the PR.
