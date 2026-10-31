---
name: panel-save-shortcut-dialogs
description: Dialogs and editors that save on Ctrl+S themselves (Site Settings, code dialog, visual designer) and how the panel shortcut leaves their key alone
---

## What if a dialog saves on Ctrl+S itself?
- It calls `preventDefault()` and the shortcut stops at `event.isDefaultPrevented()`. Dialogs that do: Site Settings (keydown on the dialog element), `codemirror_modal.js` (`Mod-s` presses its Save), the Workspace paragraph editor and the notes screen.
- Before PR #682 the shortcut went on: the dialog's form is `disable_shortcut`, so it fell back to the first visible form behind the dialog and submitted that too. Over the CSS editor the theme's CSS was saved, the page reloaded and the dialog closed before its own answer arrived: a refused setting was never shown and what was typed was gone.
- A handler that must keep the shortcut out whatever happens also calls `stopPropagation()` (the list cell editor, the designer's note dialog).
- The visual designer takes Ctrl+S on the window in the capture phase, stops it and saves the design (`_protectMainForm()` in `style_designer.js`). Focus inside `.sd-note-overlay`, `#pg_settings_modal` or the code dialog `#pgCodeEditorModal` is left alone. The code dialog was missing until #782 (PR #1244): Ctrl+S saved (or published) the whole design without the typed code and the dialog stayed open.
- `tests/save_shortcut_test.php` checks the order in the source.
