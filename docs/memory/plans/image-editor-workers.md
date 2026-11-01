---
name: image-editor-workers
description: How coordinators' workers take the free steps of plans/image-editor.md and image-editor-animation.md: which run side by side, the files a step owns, what its PR shows, the holder's review
---

## Which steps may a worker take?
- A step line that says `free` (owner, 2026-11-01: the steps go to the coordinators' workers, the holder reviews them), claimed as usual with a draft `[plan:image-editor] E<n> — <topic>`. "After En" means once En is merged; the holder keeps the lines without `free`.
- Side by side (owner, 2026-11-01): E6, E7 and E8, which change no server code (their layers reach the save in the overlay), and one save-path step.
- One at a time, since they change the save path (`pg_image_edit_validate()`, `pg_image_edit_apply()`, `pg_image_editor_save()`, `includes/image_engine.php`): E11, then E10, E12, E13, then AN4 and AN7's validator in that order.
- Later, as their lines say: E9 after E6, E7, E8; E14 after E6; E15 after E7 and E14; E16 and E17 after E9 and E11; E18 after E9; E20 stays with the holder. E10b changes `pg_image_editor_render()` and the redact part, not the save path, and runs beside any step.
- The animation plan's lines (`image-editor-animation-steps.md`) are taken and reviewed the same way, claimed as `[plan:image-editor-animation] AN<n> — <topic>`; its parts are `motion`, `render` and `scenes` and the worker `pg_image_editor_encode.js`.

## Which files does a step own?
- Its tool file `assets/js/pg_image_editor_<tool>.js` (E6 draw, E7 text, E8 sticker, E10 redact, E11 versions, E12 artboard, E13 export, E14 vector), its section of the model, its labels function and API actions, its stylesheet section (`ui/image-editor-parts.md`), `tests/image_editor_<tool>_test.php`, a memory topic `ui/image-editor-<tool>.md`, and its own step line in the plan.
- The core, `pg_image_editor_layers.js` or another tool's part only for a hook it needs: a small edit, named in the PR body. A clash with another open step goes to the architect (CLAUDE.md Working mode 10).

## How is a step built?
- Read the step line, its screens (`image-editor-visuals.md`, the pictures in `plans/image-editor/`), the prototype's part for it, `image-editor-design.md`, `ui/image-editor-parts.md`, and the crop, adjust and layers files as examples.
- Labels only with `php tools/tr_add.php`; rebuild the bundles; Node tests for the model section; the checks of CLAUDE.md "When a task is done".
- Try it in a browser (`workflow/sandbox-and-environment.md`) at desktop size and on a phone (390x844 and 844x390, touch): draw or type, move, resize, undo and redo, save over a file and as a copy, reopen. The PR body says what was tried on both and what could not be.

## How is it reviewed?
- With the checks passing, the worker comments "Ready for review" on its draft PR and waits for the holder (a stated wait, not a stale draft). The holder tries it against the screens on desktop and phone and answers on the PR: changes, or "Reviewed".
- After "Reviewed" the worker marks the PR ready (CI runs) and adds the `ready` label once green (CLAUDE.md Working mode 2); the merging session merges it.
