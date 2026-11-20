---
name: image-editor-follow
description: The Artifact Canvas's Follow (Animate tool): a layer moves with an area boxed on a video, by an own template-matching tracker; its numbers, limits and checks
module: image_editor
---

## What does it do?
- In Animate, with one movable layer that is not a video selected while a video shows at the playhead: Follow (Path tab beside Record, and the bar over the object) arms; a drag draws a box (12 px a side at least); the clip's frames from the playhead are searched for that patch; the layer keeps its place at the playhead and moves as the patch moved. Plain linear keys in `anim.keys`, one undo step.
- Owner (2026-11-16): asked for it; accepted the look in poll 110 (`workflow/decisions-made-product-69.md`).
- Files: part `follow` (`pg_image_editor_follow.js`, after `record`, labels `pg_image_editor_strings_follow()`), the tracker `pg_image_editor_track.js` (own bundle, `config.track`), the model's `FOLLOW` section. Built in #9973 (tracker), #9997 (command), #10123 (bar, phone); a small thing #10538.

## How does the tracker work?
- Zero-mean normalised cross-correlation of the first frame's patch, frames grey at 320 px on the longer side. Search round the last place plus the last move, radius max(16 px, half the box's longer side); sub-pixel peak by a parabola.
- A small thing (GC3): where the box's shorter side would be under 24 px at 320, frames are read at the scale that makes it 24 px, never above the clip's own pixels (`M.followSize(w, h, side)`), and only the region round the last place: each answer's `want`, the first frame `M.followRegion()`. At 320 px a ship 2 % wide was a few pixels and was lost.
- Template renewed by 5 % of a patch scoring at least 0.85; back to the first template where that scores under 0.5 (drift). Lost: under 0.6 five frames running, or under half the box inside the frame; the keys stop at the last good frame. `lost.why`: `edge` (left the picture) or `match`.
- Lost message (`M.followLostText()`): why and what helps (`followLostSmall`, `followLostMatch`) the first time in the editor only, then the short `followLost`; leaving the picture: `followLostEdge`. Owner (2026-11-20): warned on every try (`workflow/decisions-made-product-80.md`).
- A worker, or on the page one frame a slice where a site refuses the worker (`pageSession()`). Frames come from a decoder of its own on the file's bytes, each source frame once, at most 30 a second.

## What is left out, and why?
- Size and turn of the followed thing, perspective, a mask, following backward, several areas, a thing named in words; no model or library (`image-editor-overview.md`).
- A clip moved by its own keys counts as standing still. The keys are plain: a clip trimmed, moved, split or sped up later is not followed again (the Path tab says so).
- Template matching likes a box inside the thing: ground inside the box that does not move with it lowers the score. On the fixture a box 6 px wider each side than the square was lost at 0.9 s; drawn inside, it held 10 s.

## How does a phone show it?
- Arming folds the sheet as Record does (back when it ends, Path tab open); the pill over the stage holds the hint, then the share and İptal Et; on a touch screen the hints name no key (`M.followHint()`, the architect's look on #10123); a finger draws the box; the bar at the stage's foot hides while armed.

## How was it checked?
- `tests/image_editor_follow_test.php` (Node): moves within 1.5 px with noise and light change, lost at the frame, the mapping for a turned and a mirrored clip, span, times, keys, the bar's list, the fixture's 300 frames; regions give the same points as whole frames; the owner's case (26 x 16 ship, 1280 x 720, moving water): lost after 12 frames at 320 px, held 90 within 1.5 px read near.
- Chromium on `tests/fixtures/video/follow_square.webm` (VP9 640×360, 10 s): 4.4–5.3 s for 10 s, keys within 2.6 px; 1440, 1024, 390 (touch), light, dark, both languages; worker refused. Not tried: Safari, Firefox, H.264; the region read (GC3) only in Node.
