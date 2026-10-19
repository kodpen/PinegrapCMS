---
name: panel-save-shortcut
description: The panel's Ctrl+S shortcut (backend.src.js): which form it submits, disable_shortcut, and why a key a dialog has already handled is left alone.
---

## Which form does Ctrl+S submit on a panel screen?
- `backend.src.js`, the window `keydown` block, `case 's'`: the form around the focused element, skipping `form.disable_shortcut` (forms that only delete, and the Site Settings dialog's form); without one, the first visible such form on the screen. It presses the form's `submitshortcut` button (default `submit_save`), otherwise submits the form.
- The visitor page (`frontend.src.js`) has its own block, which only presses the inline editing Save button.

## What if a dialog saves on Ctrl+S itself?
- It calls `preventDefault()` and the shortcut stops at `event.isDefaultPrevented()`. Dialogs that do: Site Settings (keydown on the dialog element), `codemirror_modal.js` (`Mod-s` presses its Save), the Workspace paragraph editor and the notes screen.
- Before PR #NNN the shortcut went on: the dialog's form is `disable_shortcut`, so it fell back to the first visible form behind the dialog and submitted that too. Over the CSS editor the theme's CSS was saved, the page reloaded and the dialog closed before its own answer arrived: a refused setting was never shown and what was typed was gone.
- A handler that must keep the shortcut out whatever happens also calls `stopPropagation()` (the list cell editor, the designer's note dialog).
- `tests/save_shortcut_test.php` checks the order in the source.
