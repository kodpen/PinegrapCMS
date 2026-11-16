---
name: design-canvas-quick-reach-steps
description: Plan design-canvas-quick-reach, steps QR1 to QR4: quick split, selecting from any tool, the bar over the object with the Motion popover, the track row's button and close-out
---

## Which steps are there?
- QR1 (M) Quick split: R1 to R6. After the fix of issue #9950 has landed.
  - Files: `assets/js/pg_image_editor_clip.js` (the bar's button, the chip, the one method), the model's `CLIP` section (`clipSplitPick()`), `pg_image_editor_access.js` (the key, the `?` sheet), `pg_image_editor_strings_clip()`, the clip's rules in `pg_image_editor.src.css`.
  - Tests, red first: `tests/image_editor_clip_test.php` for `clipSplitPick()` (selected and under the playhead, selected elsewhere, none selected, two rows, too near an edge), `tests/image_editor_access_test.php` for the key.
  - Pictures in the agent channel for the architect: the play bar at 1440 and 1024 with and without a clip to split, the chip on the row, light and dark.

- QR2 (M) after QR1: selecting from any tool, R7 to R12.
  - Files: `pg_image_editor.js` (the press's dispatch), `pg_image_editor_layers.js`, `pg_image_editor_motion.js` and `pg_image_editor_keyframes.js` (R10), `pg_image_editor_text.js` (R11), the parts R12's census names, the model (a pure `pressSelects()` beside the layers maths).
  - The census table comes first in the PR body. A tool whose press cannot be changed without breaking its own work is left and named.
  - No new look: nothing is drawn that was not there.

- QR3 (L) after QR2: the bar over the object and the Motion popover, R13 to R22.
  - Files: `pg_image_editor_actions.js`, the model beside `actionList()`, `pg_image_editor_motion.js` (the popover's tiles come from the panel's own builder), `pg_image_editor_menu.js` (More), `pg_image_editor_strings_actions()`, the actions' rules in the stylesheet.
  - Over about 1,000 product lines the popover (R19 to R22) moves to a step QR3b after it; the bar then lands with Motion left out and the PR says so.
  - The owner's look: the pictures `plans/design-canvas-quick-reach.md` lists; not marked ready before it.

- QR4 (S) after QR3: R23, R24's check, and the close-out.
  - Every R number checked against the code with its PR, in the PR body.
  - Memory rewritten to what is built: `ui/image-editor-actions.md` (the desktop's bar, its list and place), `ui/image-editor-clip-timeline.md` (Split's bar button, key and chip), `ui/image-editor-select.md` (R8 to R12), `ui/image-editor-motion.md` (the popover, the playhead kept on a click), `ui/image-editor-tracks.md`.
  - The owner's decision goes into the product decisions list with the PR numbers; this plan's four files are deleted.

## What runs beside it?
- The steps go one at a time: they share the core, the layers file and the actions part.
- `plans/design-canvas-follow.md`'s FW1 (a new part and a model section of its own) may run beside any of them (`ui/image-editor-parts.md`: tools built side by side change different lines).

## What does every step do?
- Run `php tools/test.php image_editor`, `node --check` on each changed script, `php tools/build_js.php` and `build_css.php` to try (bundles never committed), `php tools/check_lang.php`.
- A browser run is allowed here where a unit test cannot reach pointer handling or the bar's place; the PR says what was tried at which size.
