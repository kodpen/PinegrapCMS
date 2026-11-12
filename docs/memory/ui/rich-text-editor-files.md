---
name: rich-text-editor-files
description: Who the rich text editor (CKEditor 4) offers file browsing and uploading to; the upload addresses carry the session token
module: editor
---

## Who gets Browse and Upload in the rich text editor?
- `get_wysiwyg_editor_code()` (CKEditor branch) takes every file setting from `_pg_wysiwyg_file_properties()` (`includes/fn/editor.php`): `filebrowserUploadMethod`; no browse window (the link window was deleted in #8896, the image window in PK9: both dialogs' Browse Server open the picker, `picker-editor-bridge.md`) and both upload addresses (`editor_add_file.php?token=…&folder_id=…`).
- Behind the same gate it prints the bridge to the picker (`pg_editor_bridge_script()`): in the panel the Link and Image dialogs choose through `pgPicker`, the image browser stays only as the fallback without `pgUI` (`picker-editor-bridge.md`).
- They come only for a logged-in user of role 1–2 or a role-3 user with edit access to a folder (`no_acl_check()`), the same gate `editor_add_file.php` applies. Anyone else, a visitor on a site form with a rich-text field included, gets none: no Browse button, no Upload tab in the Link and Image dialogs.
- The TinyMCE ("previous") branch has no upload setting.

## What was the symptom?
- The upload addresses were outside the gate: a visitor saw an Upload tab whose every upload was refused ("you are not logged in"), and the page carried the session token in its markup.
- Fixed in the editor bug hunt slice after #6032. Checked there and clean: theme stylesheet and custom formats (file names are ASCII-safe, `escape_javascript()` on class names and elements), editor ids (`pg_cf_rich_text_ids()` and numeric field names), `pg_code_editor_attach()` and the other `pg_code_editor_*` helpers.

## How is it checked?
- `tests/editor_file_properties_test.php`: refused gives nothing, allowed gives the image browser and both upload addresses, the folder is an integer, and only the helper names the upload endpoint.
