---
name: ai-write-tinymce
description: "Write with AI" and the AI marks with the "previous" rich text editor (TinyMCE 3): where the draft goes, how the mark follows the text, the lost-draft symptom it fixed
---

## When does it apply?
- Settings › Appearance › Rich-text editor › Editor version "previous" (`config.page_editor_version = 'previous'`): `get_wysiwyg_editor_code()` runs TinyMCE 3.5.10 instead of CKEditor on the product's Full Description and on the rich text fields of form records (`add_submitted_form.php`, `edit_submitted_form.php`).
- TinyMCE keeps its own copy of the text in its frame and writes it into the textarea only when the form is sent (`tinymce.triggerSave()` in `product_builder.js`, its own submit hook). Until then the textarea holds the text the page was drawn with.

## Where does a draft go?
- `assets/js/pg_ai_write.js` `tinyOf(id)`: `tinymce.get(id)` once it is `initialized` and not hidden; while it loads the textarea is the field (TinyMCE reads it at start).
- The context is read with `getContent()`, so unsaved edits count.
- "Use this text" with the html format: `execCommand('mceSetContent', false, html)` (one undo step, raises the editor's change event), then `save()` writes the textarea too, then `pg:ai-write-used`. CKEditor keeps `setData()` with its callback.

## How does the mark follow the text?
- `assets/js/pg_ai_fields.js` reads the text of a TinyMCE field with `getContent()` (`textOf()`), the same text `triggerSave()` posts, so the fingerprint the save stores is the one of the text sent.
- Editors are watched when they start (`tinymce.onAddEditor`, then each editor's `onInit`; editors already there are looped). Followed events: `onChange` (each undo step), `onKeyUp`, `onSetContent` (the HTML source dialog), `onUndo`, `onRedo`. A mark put on before the editor ran takes the editor's text.
- The capture-phase `submit` check runs before the form's own listeners; it reads the editor, not the stale textarea.

## What was broken before?
- #995 (PR #1051): only `CKEDITOR.instances` was looked at. "Use this text" wrote the hidden textarea: the badge showed, but the editor kept the old text, `triggerSave()` put it back on save and the old hand-written text was stored with the "AI" mark. A hand edit in TinyMCE never took a mark off, so the person's own text was saved as written with AI.
- Checked with Playwright on the sandbox (stubbed `ai_write` answer): draft in the editor and saved with its fingerprint; a hand edit and the save clear `ai_fields`; Ctrl+Z after a hand edit puts the mark back; CKEditor unchanged.
