---
name: panel-save-shortcut
description: Panel Ctrl+S (backend.src.js) and Return in a text field: which form and button each submits, the button hint, disable_shortcut forms, a key a dialog handled, buttons on their own form
---

## Which form does Ctrl+S submit on a panel screen?
- `backend.src.js`, the window `keydown` block, `case 's'`: the form around the focused element, skipping `form.disable_shortcut`; without one, the first visible such form on the screen. It presses the form's `submitshortcut` button (default `submit_save`), otherwise submits the form.
- On ready every form without `disable_shortcut` gets the hint `Ctrl+S | ⌘+S` in the title of that button (or of its first submit button), added after a title it already has.
- The visitor page (`frontend.src.js`) has its own block, which only presses the inline editing Save button.

## Which forms carry `disable_shortcut`?
- Forms that only delete (the shortcut is too dangerous there) and helper forms that are never "the" form of a screen: the header search box, the account menu's quick form, `pg_filter_select()`'s filter, the licence check, the Site Settings dialog's form. The dashboard's widget settings, once a form in the Panel row's menu panel, are a card of Site Settings > Appearance now (`settings-dashboard-card.md`).
- A screen that answers Ctrl+S itself puts it on its form and catches the key: the visual editor (`#style_designer_form`).

## What if a dialog saves on Ctrl+S itself?
- It calls `preventDefault()` and the shortcut stops at `event.isDefaultPrevented()`. Dialogs that do: Site Settings (keydown on the dialog element), `codemirror_modal.js` (`Mod-s` presses its Save), the Workspace paragraph editor and the notes screen.
- Before PR #682 the shortcut went on: the dialog's form is `disable_shortcut`, so it fell back to the first visible form behind the dialog and submitted that too. Over the CSS editor the theme's CSS was saved, the page reloaded and the dialog closed before its own answer arrived: a refused setting was never shown and what was typed was gone.
- A handler that must keep the shortcut out whatever happens also calls `stopPropagation()` (the list cell editor, the designer's note dialog).
- The visual designer takes Ctrl+S on the window in the capture phase, stops it and saves the design (`_protectMainForm()` in `style_designer.js`). Focus inside `.sd-note-overlay`, `#pg_settings_modal` or the code dialog `#pgCodeEditorModal` is left alone. The code dialog was missing until #782 (PR #1244): Ctrl+S saved (or published) the whole design without the typed code and the dialog stayed open.
- `tests/save_shortcut_test.php` checks the order in the source.

## And Return in a text field?
- No script handles it: the browser submits the form with its first submit button. A screen whose first one is not Save runs that button on Return: on an image `edit_file.php` drew Rotate Left first, so Return in the name field turned the picture and dropped the new name (#1041, fixed in PR #1064).
- A button that does its own job (rotate, connect, align) goes on a form of its own: the button gets `form="<id>"`, the form sits outside the edit form with `class="disable_shortcut d-none"` and its own token. Examples: `edit_file.php` (`edit_file_rotate`), `search_console.php`, `smtp_settings.php`, `erp_account_sync_item.php`. Return then reaches the edit form's first button (Save & Return); Ctrl+S is unchanged.
- A form that keeps its action buttons (drawers) starts with a hidden nameless submit button instead; the guided tour's Enter: `return-key-default-button.md`.
- `edit_file.php` tells a submission from a page view by `$_POST['name']`, so its rotate form also posts the stored name.
