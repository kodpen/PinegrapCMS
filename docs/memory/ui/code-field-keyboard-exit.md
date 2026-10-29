---
name: code-field-keyboard-exit
description: Leaving a code field from the keyboard: Tab indents, Ctrl+M (Mac Shift+Alt+M) switches Tab to moving focus, the hint line under an editable field and its aria-describedby
---

## What does Tab do in a code field?
- It indents (Shift+Tab outdents): `pgCeCreate()` in `assets/js/pg_code_editor.js` binds `indentWithTab` unless `indentWithTab: false`. A read-only field refuses the indent, so Tab moves on there.
- Ctrl+M (Shift+Alt+M on a Mac) runs CodeMirror's `toggleTabFocusMode`: Tab and Shift+Tab move focus until the key is pressed again. It sits in the facade's own keymap (`keymapExtensions()`), so every editor of the facade has it: panel fields, the designer's code dialog, the File Manager window. The engine binds it only in `defaultKeymap`; the vscode keymap the facade uses by default has no such key.
- CodeMirror's own rule (Escape, then Tab within 2 seconds, moves focus) still works on full-page screens. Inside a dialog a free Escape goes on to the dialog by design (`code-editor-escape-in-dialogs.md`): in Site Settings it asks "Close anyway?" or closes, so Ctrl+M is the way out there.

## How does a user learn it?
- `pg_code_editor_attach()` (`includes/fn/editor.php`) puts a line at the left of the tools row under an editable field: "Tab indents. {var} makes Tab move to the next field.", `{var}` filled with `Ctrl+M` or `Shift+Alt+M` by platform. Its id is `<field id>_keys_hint`, passed as the `describedBy` option; `attach()` puts it in the content's `aria-describedby` next to the `aria-label`, so a screen reader reads it on entering the field. A read-only field gets no line.

## What was the symptom?
- A keyboard user tabbing through a form stopped in the first code field (Login Region has four in a row; theme CSS, JavaScript, custom style, product builder, form list view): Tab only indented. Inside Site Settings (Custom CSS, sitemap/robots/llms extra content, social networking code, custom JSON-LD) the hidden Escape-then-Tab rule closed the dialog instead.

## How is it checked?
- `tests/code_editor_test.php` (`test_code_editor_ctrl_m_switches_tab_to_moving_focus`, `test_code_editor_attach_tells_how_to_leave_the_field`); `tests/content_edit_names_test.php` pins the `aria-label`.

Related: docs/memory/ui/panel-code-fields.md, docs/memory/ui/code-editor-escape-in-dialogs.md
