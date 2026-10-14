---
name: settings-modal-autofill-lock
description: Settings dialog autofill prevention: readonly latch/unlatch of text fields, stamped autocomplete attributes, no fake password field in the dialog.
---

## Why do text fields arrive locked?
- `latch()` / `unlatch()`: JavaScript puts `readonly` + `data-pgro` on every visible `input`/`textarea`; the first `pointerdown` / `keydown` / `focusin` (document, CAPTURE phase, since masks and the time picker bind on focus) unlocks. Autofill and password managers skip `readonly` fields; no settings field needs to be filled by anyone.
- The lock comes from the script, not the server: if the script fails the field is simply not locked and types as usual (the reverse would lock the whole form the day the script breaks).
- Checkbox / radio / select ignore `readonly`, so they are skipped. A field the screen locked itself carries no mark and stays locked.
- Bootstrap paints `[readonly]` grey, so `[data-pgro]` takes the colour back: a closed field must not look disabled.

## What does `pg_settings_stamp_autocomplete()` do?
- Stamps every control with `autocomplete="pg-no-autofill"` (`"off"` is not enough: Chrome ignores it on a field it is sure about).
- Also stamps vendor opt-outs: `data-lpignore`, `data-1p-ignore`, `data-bwignore`, `data-form-type`. Harmless for managers that do not know them; this is the polite half of the defence, the restore (settings-modal-autofill-restore) does the actual job.

## Why is there no fake password field in the dialog?
- The screen used to carry two fake fields; one was a hidden `type="password"` and it WAS the problem: a hidden password input in a form tells a password manager "this is a login form". Kaspersky answered by typing the saved username into the support e-mail field (field became "admin"). Removing the field stopped the filling.
- Fake fields were guarding against Chrome's own autofill; the lock above replaced that and Chrome skips `readonly`. Do NOT put it back.
- `screen.php`'s page flow also lost the password decoy; the text decoy stays.

Related: docs/memory/ui/settings-modal-autofill-restore.md
