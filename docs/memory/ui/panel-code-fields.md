---
name: panel-code-fields
description: Code fields on panel screens: pg_code_editor_attach(), loading through pgCodeEditorUse(), the settings dialog, 'code_editor' => false, the colour scheme, which screens have moved
---

## How does a panel screen draw a code field?
- `pg_code_editor_attach(array('id' => 'code', 'code_type' => 'css', 'readonly' => false))` (`includes/fn/editor.php`) after the textarea: the facade's editor (`assets/js/pg_code_editor.js`, CodeMirror 6) takes the textarea's place, keeps its value current and raises `input` on it, so the form submits as before. `code_type` takes the names `get_codemirror_javascript()` took; `pg_code_editor_language()` maps them to `css`, `javascript`, `json`, else `html`; a plain-text type turns the lint off. Optional `height`; without it the field grows to 80vh and scrolls inside; a read-only field is as tall as its text.
- F11 fills the window, Esc leaves. The instance is kept in `window.pgCodeEditors[id]` and the textarea raises `pgcodeeditorready` when it is there; `create_file.php` then follows the chosen type with `setLanguage()` and `setLint()`.

## How is the facade loaded?
- Not in the head. The panel header names the facade and its engine in `window.pgCodeEditorConfig` (`pg_code_editor_urls()`); `pg_code_editor_includes()` (once per request) adds the engine's translated texts (`pg_code_editor_phrases()`, the keys `_sdCodePhrases()` uses). Both are inline scripts: jQuery fetches a `<script src>` in pane markup with a blocking request.
- `window.pgCodeEditorUse(run)` (`backend.src.js`) fetches the facade once and runs `run` (also on failure; `run` checks `PgCodeEditor`). The engine bundle (~600 KB) follows on the first editor (`PgCodeEditor.load()`).
- The designer is the exception: `designer_screen.php` puts the facade in its page, as `style_designer.js` registers completion sources at start.

## How does the settings dialog handle code fields?
- Panes arrive by AJAX; `pgCodeEditorReady(markup, done)` waits for the facade and its engine when the markup calls `pgCodeEditorUse`, so the pane is painted with its editors (and still loads CodeMirror 5 from the template for markup that mentions it).
- `pgReleaseEditors(scope)` destroys the facade editors inside the scope as well; it runs on the pane before a new one is painted, on the card's own dialogs (`clearExtras()`, the cron commands live in one) and when the dialog closes.

## What does 'code_editor' => false do?
- `pg_page_shell()` / `output_header()` with it leave CodeMirror 5 and its linters (40 files, ~2 MB) out of the head, parked in `<template id="pg-code-editor-assets">`. A screen whose code fields are the facade's sets it.
- Moved: every panel screen with a code field (`edit_file`, the content screens, `create_file`, `generate_layout`, the settings panes and pages); the File Manager's file window builds its own (`PgCodeEditor.create()` after the hidden textarea, `destroy()` on close, language from `pg_explorer_editor_language()`).
- The legacy page designer (`page_designer.js`, facade in its page): `PgCodeEditor.open()` per item, `activeLineMarker` for the region button, `insertAtCursor()` for region tags, hover preview via `posAtCoords()` + `syntaxTree()`.
- The rich text editor (CKEditor 4, `get_wysiwyg_editor_code()`, also on site pages) shows its Source mode and Source dialog in the facade through the plugin `pgcodeeditor` (`assets/js/pg_ckeditor_source.js`; it loads the facade itself where `pgCodeEditorUse()` is missing); CKEditor's own `codemirror` plugin is no longer loaded.
- Nothing draws CodeMirror 5 any more; `get_codemirror_includes()`, the template, the CodeMirror half of `pgCodeEditorReady()`, `assets/lib/codemirror/` and CKEditor's `plugins/codemirror/` are what is left to delete.

## How does the colour scheme reach the editors?
- The server reads the `prefers-color-scheme` cookie (`pg_code_editor_theme()`) for the first paint; the panel's scheme switch (`setTheme()` in `backend.src.js`) pushes `dark`/`light` to CodeMirror 5 and, through `window.pgCodeEditors`, to every facade editor.
