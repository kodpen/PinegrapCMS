---
name: design-canvas-follow-steps
description: Plan design-canvas-follow, steps FW1 to FW3: the tracker and its tests, the Follow command in the Animate tool, then the bar's button, the phone and the close-out
---

## Which steps are there?
- FW1 (M) The tracker: F6 to F9 and F15. No screen. Done: #9973.
  - New: `assets/js/pg_image_editor_track.js` (the tracker as a worker and as a page object, its maths reachable from Node as Select subject's is), its bundle in `pg_js_bundles()` and its address in `pg_image_editor_config()`, `tests/image_editor_follow_test.php`.
  - The landing builds the `.min.js`; the PR commits none.
  - May run beside `plans/design-canvas-quick-reach.md`'s steps: no file of theirs is edited but the bundle list.

- FW2 (M) after FW1 and after the fix of issue #9950: the command, F1 to F5 and F10 to F14, F16.
  - New: `assets/js/pg_image_editor_follow.js` (part `follow`, bundled after `record`; it hangs its button on the Path tab as the record part does), `pg_image_editor_strings_follow()`, its rules under its line in `pg_image_editor.src.css`, a `FOLLOW` section in the model for F10.
  - Existing files, small edits named in the PR: `pg_image_editor_parts()` and the bundle list, `pg_image_editor_clip.js` only if a source of its own needs a door that is not there.
  - Turkish with `php tools/tr_add.php` ("Follow" is "Takip et").
  - The owner's look: the pictures and the recording `plans/design-canvas-follow.md` lists; not marked ready before it.

- FW3 (S) after FW2, and after QR3 of `plans/design-canvas-quick-reach.md` where that has landed: the rest and the close-out.
  - Follow in the bar over the object (that plan's R15) for a layer over a video in Animate.
  - The phone: arming folds the sheet and shows the pill, as Record does (`ui/image-editor-record.md`); a finger draws the box. Pictures at 390 for the architect.
  - Every F number checked against the code with its PR, in the PR body.
  - Memory: a new `ui/image-editor-follow.md` (what it does, the method and its numbers, what it leaves out and why, how it was checked), a line in `ui/image-editor-animation.md` and `ui/image-editor-overview.md`; the owner's decision into the product decisions list; this plan's three files deleted.

## What does every step do?
- Run `php tools/test.php image_editor`, `node --check` on each changed script, `php tools/build_js.php` and `build_css.php` to try (bundles never committed), `php tools/check_lang.php`.
- A browser run is needed in FW2 (a unit test cannot reach `VideoDecoder`); the PR says what was tried at which size.

## What is asked of the architect, not decided by a worker?
- A method beyond template matching, a new field in the document, a change to how keys play, anything of "What is left out" brought in.
- A fixture video over 1 MB in the repository.
