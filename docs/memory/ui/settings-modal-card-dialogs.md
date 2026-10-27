---
name: settings-modal-card-dialogs
description: Rules for a settings card that opens its own dialog inside the settings modal: extras box, data-pgdialog rename, the dialog-open scroll lock, dispose.
---

## Where do a card's own dialogs go?
- Not into the pane but into `#pg_settings_modal_extras` (`includes/settings/modal.php`): a sibling box after the settings `<dialog>`, filled from the endpoint's `modals` and cleared when the pane changes. The pane's markup is replaced on every pane change; the dialogs live outside it.
- Stacking is the browser's top layer: the dialog opened last is drawn on top, whatever the DOM order or z-index.

## Why is Bootstrap's dialog trigger renamed in cards?
- `pg_settings_own_dialog_triggers()` (`includes/settings/fragment.php`) renames `data-bs-toggle="dialog"` to `data-pgdialog="modal"`; the settings script opens the target with `pgUI.dialog.show()`.
- Bootstrap 6's data-api, clicked inside an open dialog, swaps dialogs: it shows the new one and hides the one the trigger sits in, so the settings would close. It CANNOT be prevented: Bootstrap's delegated listeners are bound on the document in the CAPTURE phase, no listener inside the document can get ahead of it and `stopPropagation()` runs too late. The only way out is that the attribute it looks for is not there.
- The dialogs themselves are NOT converted: `data-bs-dismiss="dialog"` inside them is Bootstrap's job and finds the dialog it is in. `screen.php`'s page flow also prints the cards as they are (no outer dialog there, data-api is right).

## What about the scroll lock when an inner dialog closes?
- Nothing to repair: Bootstrap 6 keeps `dialog-open` on `<html>` while any modal `<dialog>` is open, and `pg_bs6_bridge.js` re-checks it on every `close` (`pg-ui-adapter.md`). The outer settings dialog stays locked when an inner one closes.

## How is the extras box cleaned up?
- On pane change and on dialog close, `clearExtras()` (`backend.src.js`) releases the code editors (`pgReleaseEditors()`), calls `pgUI.dialog.dispose()` on every `.dialog` in it, then empties it: an instance whose element is pulled from under it is left with nothing to close it.

Related: docs/memory/ui/settings-card-save-rules.md
