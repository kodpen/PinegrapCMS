---
name: volunteer-document-file
description: A volunteer document's file: a files row (attachment 1, folder 0, volunteer-doc- name), get_file.php's door, and its exclusion from the panel search and the file pickers
---

## Who opens a document's file?
- `get_file.php`, before the folder's check: an attachment a `volunteer_documents` row holds opens for roles 0-2, the programme's coordinators, the volunteer signed in (by contact) and the volunteer of the session's signed link (`$_SESSION['volunteer']['ids']`); `pg_volunteer_document_access_verdict()`.
- Anyone else: sign in, or 403; answered `private, no-store`; the form attachment check is skipped. A document file is written with `attachment = 1`, or the door is not asked.

## Where is a document's file stored?
- Architect (2026-11-21, decision schema-47): a `files` row with `attachment = 1`, `folder = 0` and a `volunteer-doc-` name, so it is in no File Manager folder.
- Condition: uploading is never opened without leaving these rows out of the panel search and the file pickers. Done with the upload (VS4c, `core/volunteer-document-upload.md`).

## How are they kept out of the search and the pickers?
- `pg_volunteer_doc_files_sql()` (`includes/fn/files.php`, prefix `PG_VOLUNTEER_DOC_PREFIX`) adds `name NOT LIKE 'volunteer-doc-%'` to the panel search's files (`includes/panel/search.php`) and the image editor's picture, sound and clip pickers (`pg_image_editor_sticker_where()`, `pg_image_editor_sound_where()`), which read files of every folder for roles 0-2.
- The shared picker (`pg_picker_files_where()`), the editor's TinyMCE lists and the API's files list already take `attachment = 0` only; the Workspace picker takes `folder > 0`. A new files reader that reaches folder 0 or attachments adds the condition.

Related: docs/memory/core/volunteer-store.md, docs/memory/core/volunteer-shift-rules.md
