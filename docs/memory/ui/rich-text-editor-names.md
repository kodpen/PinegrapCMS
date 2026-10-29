---
name: rich-text-editor-names
description: The rich text editor (CKEditor 4) named by its field's caption through pgCkeditorReplace(); label click focuses it; Source view names the code editor, says how Tab leaves, passes Alt+F10/Alt+0 on
---

## How is a rich text editor named?
- `get_wysiwyg_editor_code()` (`includes/fn/editor.php`, CKEditor branch, not `edit_region_dialog`) starts each field with `pgCkeditorReplace(id, software_ckeditor_config)` (`assets/js/pg_ckeditor_source.js`), not `CKEDITOR.replace()`.
- The helper reads the textarea's name by the code editor's rule (`pgCeTextareaName()`): `aria-label`, else the `aria-labelledby` text, else the first label without its `aria-hidden` parts. With a name it replaces with a copy of the config: `title` = name (iframe `title`, body `aria-label`), `applicationTitle` = HTML-encoded name (the role=application wrapper's voice label, which CKEditor puts into its markup unencoded). Without one the shared `title: false` stays.
- The textarea's labels get a click handler that calls `editor.focus()`: CKEditor hides the textarea, so the label reached nothing.
- Site custom forms use the same helper: same name, no markup or visual change.

## What was the symptom?
- The shared config had `title: false`: no iframe title, no body name. The wrapper read "Rich Text Editor, <element id>" (lang/tr.js leaves "application" in English), "Rich Text Editor, 123" for a submitted-form field. The visible caption was never spoken.

## Which fields needed markup?
- `field_form.php`: the Information h2 has `id="information_heading"`, `#information` points `aria-labelledby` at it (no label before).
- `page_form.php`: the custom form's confirmation and return editors, both captioned "Message", are labelled by "legend label" (`custom_form_confirmation_type_legend custom_form_confirmation_message_label`, `custom_form_return_type_legend custom_form_return_message_label`).
- Product, product group, ad, common region, calendar event and submitted form fields already had a `<label for>`; they needed nothing.

## What does Source view do?
- CKEditor names the source textarea only for an inline editor; the plugin sets its `aria-label` from `editor.title`, and `attach()` takes it from there.
- A `form-text` line `cke_<name>_keys_hint` under `editor.container` says "Tab indents. {var} makes Tab move to the next field." (Ctrl+M, Shift+Alt+M on a Mac) and is the code editor's `describedBy`. The text comes from `pgCodeEditorConfig.labels.keysHint` (`pg_code_editor_includes()`). It goes on `beforeModeUnload`/`destroy`, and also when no code editor could be had (the plain textarea, where Tab moves on).
- CKEditor's keystroke handler listens on the hidden textarea, so keys typed in the code editor never reached it: no Alt+F10, every toolbar button `tabindex=-1`, no way back to the Source button. `forwardKeys()` passes Alt+F10 (`toolbarFocus`) and Alt+0 (`a11yHelp`) on from the host. Alt+F11 (elements path) is wysiwyg-only.
- While Source is on, the editable's `focus()` focuses the code editor, so Escape from the toolbar and the label click land there, not on the hidden textarea.

## How is it checked?
- `tests/rich_text_editor_names_test.php` (source scans). Headless Chromium on a static page with the real `ckeditor.js`: names, label click, Source line, Alt+F10, Escape, Alt+0 and the line's removal.

Related: docs/memory/ui/code-field-keyboard-exit.md, docs/memory/ui/rich-text-source-dialog.md
