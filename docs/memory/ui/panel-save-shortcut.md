---
name: panel-save-shortcut
description: The panel's Ctrl+S shortcut (backend.src.js): which form and button it presses, the hint on that button, which forms carry disable_shortcut, and why a key a dialog has already handled is left alone.
---

## Which form does Ctrl+S submit on a panel screen?
- `backend.src.js`, the window `keydown` block, `case 's'`: the form around the focused element, skipping `form.disable_shortcut`; without one, the first visible such form on the screen. It presses the form's `submitshortcut` button (default `submit_save`), otherwise submits the form.
- On ready every form without `disable_shortcut` gets the hint `Ctrl+S | ⌘+S` in the title of that button (or of its first submit button), added after a title it already has.
- The visitor page (`frontend.src.js`) has its own block, which only presses the inline editing Save button.

## Which forms carry `disable_shortcut`?
- Forms that only delete (the shortcut is too dangerous there) and helper forms that are never "the" form of a screen: the header search box, the account menu's quick form, `pg_filter_select()`'s filter, the licence check, the main menu's context forms, the Site Settings dialog's form.
- A screen that answers Ctrl+S itself puts it on its form and catches the key: the visual editor (`#style_designer_form`).

## What if a dialog saves on Ctrl+S itself?
- It calls `preventDefault()` and the shortcut stops at `event.isDefaultPrevented()`. Dialogs that do: Site Settings (keydown on the dialog element), `codemirror_modal.js` (`Mod-s` presses its Save), the Workspace paragraph editor and the notes screen.
- Before PR #682 the shortcut went on: the dialog's form is `disable_shortcut`, so it fell back to the first visible form behind the dialog and submitted that too. Over the CSS editor the theme's CSS was saved, the page reloaded and the dialog closed before its own answer arrived: a refused setting was never shown and what was typed was gone.
- A handler that must keep the shortcut out whatever happens also calls `stopPropagation()` (the list cell editor, the designer's note dialog).
- `tests/save_shortcut_test.php` checks the order in the source.
