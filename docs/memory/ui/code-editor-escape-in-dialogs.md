---
name: code-editor-escape-in-dialogs
description: Escape inside a code editor that sits in a dialog: an Escape the editor used (completion list, search panel, full window) stops at the editor; only a free one closes the dialog
---

## What does Escape do in a code editor inside a dialog?
- An Escape the editor used ends there: it closes the completion list, the search panel (Ctrl+F) or the full window (F11), and the dialog stays open with the typed code. The next Escape, which the editor leaves alone, reaches the dialog as before (the designer's code dialog cancels, Site Settings asks about unsaved changes).
- Holds for every editor of the facade (`PgCodeEditor`), so for every dialog that carries one: the designer's code dialog (`window.openCodeEditor()`, `codemirror_modal.js`), the Site Settings code fields (`pg_code_editor_attach()`), the rich text editor's Source dialog.

## How is it done?
- `pgCeCreate()` in `assets/js/pg_code_editor.js` puts a keydown listener on the editor's outer box (`view.dom`, so the search panel's fields are covered): `Escape` with `defaultPrevented` gets `stopPropagation()`. The engine prevents the default of every key a keymap command handled; a command that did nothing (no list, no panel, no full window, a single empty selection) returns false and the Escape goes on.
- Bootstrap 5.3's modal closes on every Escape that reaches its element (`keydown.dismiss.bs.modal`) without looking at `defaultPrevented`, so preventing alone was not enough.
- The designer's code dialog binds `Escape` to `_leaveFullscreen()` next to F11: the first Escape leaves `.modal-fullscreen`, the next cancels.
- The rich text Source dialog's own `keepKeys()` (`pg_ckeditor_source.js`) still stops Enter, Tab and used keys for CKEditor's dialog.

## What was the symptom?
- Typing `<di` in the designer's code window and pressing Escape to close the completion list (or Escape in its search box) closed the whole window and ran `onCancel`: the typed code was lost without a question. In Site Settings › Custom CSS, Escape in the search box or after F11 asked "Unsaved changes… close?" or closed the dialog. Fixed in #780.

## How was it checked?
- `tests/code_editor_test.php` (`test_code_editor_escape_it_used_stays_in_the_editor`).
- Headless Chromium (Playwright) on a page with Bootstrap 5.3.8, the facade and the bundle: designer dialog (completion, search, F11) and an attached field in a `keyboard: true` modal (search, F11) stay open after the first Escape; a free Escape still closes them. Before the fix each first Escape closed the dialog.
