---
name: rich-text-source-dialog
description: The rich text editor's Source dialog with the code editor: isolated in a shadow root (the dialog resets every element), sized like its textarea, Enter, Tab and used keys kept from the dialog
---

## Why is the dialog's code editor in a shadow root?
- CKEditor's dialogs carry `cke_reset_all`; the skin resets every element inside them (`.cke_reset_all *`: height and width auto, font 12px Arial, colour black, …) with the same weight as the engine's own rules and later in the page, so the reset won: the editor was as tall as its text, in Arial, and without syntax colours.
- `PgCodeEditor.attach(textarea, { isolate: true })` builds the editor inside a shadow root of its host; the engine finds the root by itself and puts its styles there. Inherited values (font size, colour) still come from the host. Fixed in #NNN (2026-10-19 test round, B3).

## How is it sized?
- sourcedialog sizes its textarea from the window (`width: 800px; height: 600px` on a wide screen) and the dialog takes the textarea's size. The editor's host takes both (`width`, `height` options of `attach()`); before, the dialog shrank to its narrowest once the textarea was hidden.

## Which keys stay in the editor?
- The dialog presses OK on Enter and moves the focus on Tab. `keepKeys()` stops Enter, Tab and any key the editor used (`defaultPrevented`: Escape closing its search or completion list) at the host. Escape the editor leaves alone still cancels the dialog. Before #NNN, Enter in the editor closed the dialog.

## Where is it, and how was it checked?
- `assets/js/pg_ckeditor_source.js` (dialog part) and `attach()` in `assets/js/pg_code_editor.js` (`width`, `isolate`, the host as `container`); tests in `tests/code_editor_test.php`.
- Sandbox with headless Chromium (Playwright), a classic page in edit mode, a region's Source button: dialog 820×718 with an 800×600 editor in the facade's font and colours (before: 388 wide, editor 36 px tall, Arial, black); Enter adds a line, Tab indents, OK writes the region, Escape cancels. The Source mode (panel product screen) is not isolated and did not change.
