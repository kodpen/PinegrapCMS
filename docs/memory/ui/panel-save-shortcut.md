---
name: panel-save-shortcut
description: The panel's Ctrl+S shortcut (backend.src.js) and Return in a text field: which form and button each submits, disable_shortcut, a key a dialog handled, buttons on a form of their own
---

## Which form does Ctrl+S submit on a panel screen?
- `backend.src.js`, the window `keydown` block, `case 's'`: the form around the focused element, skipping `form.disable_shortcut` (forms that only delete, and the Site Settings dialog's form); without one, the first visible such form on the screen. It presses the form's `submitshortcut` button (default `submit_save`), otherwise submits the form.
- The visitor page (`frontend.src.js`) has its own block, which only presses the inline editing Save button.

## What if a dialog saves on Ctrl+S itself?
- It calls `preventDefault()` and the shortcut stops at `event.isDefaultPrevented()`. Dialogs that do: Site Settings (keydown on the dialog element), `codemirror_modal.js` (`Mod-s` presses its Save), the Workspace paragraph editor and the notes screen.
- Before PR #682 the shortcut went on: the dialog's form is `disable_shortcut`, so it fell back to the first visible form behind the dialog and submitted that too. Over the CSS editor the theme's CSS was saved, the page reloaded and the dialog closed before its own answer arrived: a refused setting was never shown and what was typed was gone.
- A handler that must keep the shortcut out whatever happens also calls `stopPropagation()` (the list cell editor, the designer's note dialog).
- `tests/save_shortcut_test.php` checks the order in the source.

## And Return in a text field?
- No script handles it: the browser submits the form with its first submit button. A screen whose first one is not Save runs that button on Return: on an image `edit_file.php` drew Rotate Left first, so Return in the name field turned the picture and dropped the new name (#1041, fixed in PR #1064).
- A button that does its own job (rotate, connect, align) goes on a form of its own: the button gets `form="<id>"`, the form sits outside the edit form with `class="disable_shortcut d-none"` and its own token. Examples: `edit_file.php` (`edit_file_rotate`), `search_console.php`, `smtp_settings.php`, `erp_account_sync_item.php`. Return then reaches the edit form's first button (Save & Return); Ctrl+S is unchanged.
- `edit_file.php` tells a submission from a page view by `$_POST['name']`, so its rotate form also posts the stored name.
