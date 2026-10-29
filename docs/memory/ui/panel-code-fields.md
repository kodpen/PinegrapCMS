---
name: panel-code-fields
description: Code fields on panel screens: pg_code_editor_attach(), loading through pgCodeEditorUse(), the settings dialog, releasing editors, the rich text source mode, the colour scheme
---

## How does a panel screen draw a code field?
- `pg_code_editor_attach(array('id' => 'code', 'code_type' => 'css', 'readonly' => false))` (`includes/fn/editor.php`) after the textarea: the facade's editor (`assets/js/pg_code_editor.js`, CodeMirror 6) takes the textarea's place, keeps its value current and raises `input` on it, so the form submits as before. `pg_code_editor_language()` maps the code type to `css`, `javascript`, `json`, else `html`; a plain-text type turns the lint off. Optional `height`; without it the field grows to 80vh and scrolls inside; a read-only field is as tall as its text.
- `attach()` gives the editor's content the textarea's name (`aria-label`, labelledby, label; #2887): every code field needs one.
- The syntax lint (`_syntaxLinter()`: empty documents, files over 100 KB): docs/memory/core/code-editor-syntax-lint.md.
- Tab indents, Ctrl+M moves focus (`code-field-keyboard-exit.md`); F11 fills the window, Esc leaves. The instance is kept in `window.pgCodeEditors[id]` and the textarea raises `pgcodeeditorready` when it is there (`create_file.php` then follows the chosen type with `setLanguage()` / `setLint()`).
- CodeMirror 5 is gone (2026.11.0): no `get_codemirror_*()`, no `'code_editor'` page property, no head template; `clean_up.php` lists its old folders.

## How is the facade loaded?
- Not in the head. The panel header names the facade and its engine in `window.pgCodeEditorConfig` (`pg_code_editor_urls()`); `pg_code_editor_includes()` (once per request) adds the engine's translated texts and labels. Both are inline scripts: jQuery fetches a `<script src>` in pane markup with a blocking request.
- `window.pgCodeEditorUse(run)` (`backend.src.js`) fetches the facade once and runs `run` (also on failure; `run` checks `PgCodeEditor`). The engine bundle (~600 KB) follows on the first editor (`PgCodeEditor.load()`).
- Pages that build editors at start put the facade in the page: the designer (`designer_screen.php`) and the legacy page designer (`page_designer.php`).

## What must happen around AJAX markup?
- The settings dialog: `pgCodeEditorReady(markup, done)` waits for the facade and its engine when the markup calls `pgCodeEditorUse`, so the pane is painted with its editors.
- `window.pgReleaseEditors(scope)` destroys every editor of `window.pgCodeEditors` inside the scope (it keeps document listeners and a resize observer). Call it before replacing or closing markup that may hold one, like `destroy_widget_charts()` for charts; the settings dialog does it for the pane, the card's own dialogs (`clearExtras()`) and on close.

## Which other places use the facade?
- The File Manager's file window (`view_folders.php`): `PgCodeEditor.create()` after the hidden textarea, `destroy()` on close, language from `pg_explorer_editor_language()`.
- The legacy page designer (`page_designer.js`): `PgCodeEditor.open()` per item, `activeLineMarker` for the region button, `insertAtCursor()` for region tags, image preview on hover via `posAtCoords()` + `syntaxTree()`.
- `get_wysiwyg_editor_code()` sets `CKEDITOR.disableAutoInline = true` (a property of `CKEDITOR`, not of `CKEDITOR.config`): with the config form a code editor's `contenteditable` `.cm-content` became an inline CKEditor that asked for the missing `config.js` (#2036).
- The rich text editor (CKEditor 4, `get_wysiwyg_editor_code()`, also on site pages): Source mode and Source dialog through its plugin `pgcodeeditor` (`assets/js/pg_ckeditor_source.js`, which loads the facade itself where `pgCodeEditorUse()` is missing). The dialog: `rich-text-source-dialog.md`.

## How does the colour scheme reach the editors?
- The server reads the `prefers-color-scheme` cookie (`pg_code_editor_theme()`) for the first paint; the panel's scheme switch (`setTheme()` in `backend.src.js`) calls `setTheme()` on every editor in `window.pgCodeEditors`.
