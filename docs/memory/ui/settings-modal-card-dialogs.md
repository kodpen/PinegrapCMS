---
name: settings-modal-card-dialogs
description: Rules for a settings card that opens its own Bootstrap dialog inside the settings modal: extras box, data-pgdialog rename, modal-open, dispose.
---

## Where do a card's own dialogs go?
- Not into the pane but into `#pg_settings_modal_extras`: a sibling box that comes AFTER the settings dialog. A nested modal sits behind the outer one's backdrop; both are `z-index: 1055`, so DOM order decides.

## Why is Bootstrap's modal trigger renamed in cards?
- `pg_settings_own_dialog_triggers()` renames `data-bs-toggle="modal"` to `data-pgdialog="modal"`.
- Bootstrap's `data-api` closes the open modal before showing the new one and this CANNOT be prevented: Bootstrap's delegated listeners are bound on the document in the CAPTURE phase, no listener inside the document can get ahead of it and `stopPropagation()` runs too late. The only way out is that the attribute it looks for is not there.
- The dialogs themselves are NOT converted: `data-bs-dismiss` inside them is Bootstrap's job and finds the dialog it is in. `screen.php`'s page flow also prints the cards as they are (no outer modal there, data-api is right).

## What must be repaired when an inner dialog closes?
- It takes the `modal-open` class off `body` and leaves. Bootstrap manages it as if there were a single modal; the outer one is still open and the screen behind starts scrolling again. Put the class back on `hidden.bs.modal`.

## How is the extras box cleaned up?
- On pane change and on dialog close, extras is `dispose()`d, not merely emptied: a Bootstrap instance whose element is pulled from under it leaves its backdrop on the page and nothing is left to close it.

Related: docs/memory/ui/settings-card-save-rules.md
